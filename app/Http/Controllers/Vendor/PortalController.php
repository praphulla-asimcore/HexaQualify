<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\SubmissionReceivedMail;
use App\Mail\SubmittedMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Setting;
use App\Models\VendorResponse;
use App\Services\OtpService;
use App\Services\Scoring\ScoringEngine;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Vendor portal (§4.3). Authenticated by the invitation token plus an email OTP.
 *
 * Everything returned here comes from the vendor-safe structure_json. Nothing in this
 * controller may load the scoring guide, scores or decisions.
 */
class PortalController extends Controller
{
    public const TEXT_MAX = 3000;

    private const VENDOR_FIELDS = [
        'company' => 200, 'pan' => 30, 'contact_person' => 150, 'designation' => 150, 'email' => 200, 'phone' => 40,
    ];

    public function __construct(private OtpService $otp) {}

    public function landing(Request $request, string $token)
    {
        $invitation = Invitation::findByToken($token);
        if ($closed = $this->closed($invitation, $request)) {
            return $closed;
        }
        if ($this->verified($request, $invitation)) {
            return redirect()->route('vendor.form', $token);
        }
        if (! $invitation->opened_at) {
            $invitation->opened_at = now();
            $invitation->save();
            $invitation->advanceTo('opened');
            AuditLog::record('invitation.opened', 'invitation', $invitation->id, actorType: 'vendor', actorId: $invitation->id);
        }

        $locked = $this->otp->lockedUntil($invitation);

        return Inertia::render('Vendor/Landing', [
            'token' => $token,
            'questionnaire' => [
                'name' => $invitation->version->questionnaire->name,
                'description' => $invitation->version->questionnaire->description,
            ],
            'vendor_company' => $invitation->vendor_company,
            'email' => $this->mask($invitation->contact_email),
            'deadline' => $invitation->expires_at->toIso8601String(),
            'codeSent' => (bool) $request->session()->get('otp_sent'),
            'lockedUntil' => $locked?->format(DATE_ATOM),
        ]);
    }

    public function sendOtp(Request $request, string $token)
    {
        $invitation = Invitation::findByToken($token);
        if ($this->closed($invitation, $request)) {
            return redirect()->route('vendor.landing', $token);
        }
        $this->otp->send($invitation);

        return redirect()->route('vendor.landing', $token)->with('otp_sent', true);
    }

    public function verifyOtp(Request $request, string $token)
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $invitation = Invitation::findByToken($token);
        if ($this->closed($invitation, $request)) {
            return redirect()->route('vendor.landing', $token);
        }

        $result = $this->otp->verify($invitation, $request->input('code'));
        if ($result !== 'ok') {
            $messages = [
                'invalid' => 'That code is not correct. Please try again.',
                'expired' => 'That code has expired. Request a new code.',
                'locked' => 'Too many attempts. Please wait 15 minutes and request a new code.',
            ];

            return redirect()->route('vendor.landing', $token)->with('otp_sent', $result === 'invalid')
                ->withErrors(['code' => $messages[$result]]);
        }

        $request->session()->regenerate();
        $request->session()->put('vendor_invitations.'.$invitation->id, now()->timestamp);

        return redirect()->route('vendor.form', $token);
    }

    public function form(Request $request, string $token)
    {
        $invitation = Invitation::findByToken($token);
        if ($closed = $this->closed($invitation, $request)) {
            return $closed;
        }
        if (! $this->verified($request, $invitation)) {
            return redirect()->route('vendor.landing', $token);
        }

        $response = $invitation->response;
        $version = $invitation->version;

        return Inertia::render('Vendor/Form', [
            'token' => $token,
            'questionnaire' => [
                'name' => $version->questionnaire->name,
                'description' => $version->questionnaire->description,
                'structure' => $version->structure_json,
            ],
            'deadline' => $invitation->expires_at->toIso8601String(),
            'vendor' => (object) ($response?->vendor_json ?? [
                'company' => $invitation->vendor_company,
                'contact_person' => $invitation->contact_name,
                'email' => $invitation->contact_email,
            ]),
            'answers' => (object) ($response?->answers_json ?? []),
            'savedAt' => $response?->updated_at?->toIso8601String(),
        ]);
    }

    public function draft(Request $request, string $token)
    {
        $invitation = $this->authorised($request, $token);
        [$vendor, $answers] = $this->clean($invitation, $request);

        $response = VendorResponse::firstOrNew(['invitation_id' => $invitation->id]);
        abort_if($response->isSubmitted(), 409);
        $response->fill(['vendor_json' => $vendor, 'answers_json' => $answers])->save();
        $response->touch();
        if ($invitation->status !== 'in_progress') {
            $invitation->advanceTo('in_progress');
        }

        return response()->json(['savedAt' => $response->updated_at->toIso8601String()]);
    }

    public function submit(Request $request, string $token, ScoringService $scoring)
    {
        $invitation = $this->authorised($request, $token);
        [$vendor, $answers] = $this->clean($invitation, $request);
        $this->validateComplete($invitation, $vendor, $answers, $request->boolean('declaration'));

        $response = DB::transaction(function () use ($invitation, $vendor, $answers, $request) {
            $inv = Invitation::whereKey($invitation->id)->lockForUpdate()->first();
            abort_unless($inv->acceptsAnswers(), 409, 'This questionnaire can no longer be submitted.');
            $response = VendorResponse::firstOrNew(['invitation_id' => $inv->id]);
            abort_if($response->isSubmitted(), 409);
            $response->fill([
                'vendor_json' => $vendor,
                'answers_json' => $answers,
                'declaration_at' => now(),
                'submitted_at' => now(),
                'submitted_ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ])->save();
            $inv->update(['status' => 'submitted']);
            AuditLog::record('response.submit', 'response', $response->id, null, ['invitation_id' => $inv->id],
                'vendor', $inv->id);

            return $response;
        });

        // Score immediately so staff see the result within seconds.
        $scoring->score($response->fresh('invitation.version.guide'));

        Mail::to($invitation->contact_email)->queue(new SubmissionReceivedMail($invitation));

        $recipients = array_filter(array_merge([$invitation->creator?->email], Setting::get('distribution_list') ?? []));
        if ($recipients) {
            Mail::to(array_values(array_unique($recipients)))->queue(new SubmittedMail($response));
        }

        return redirect()->route('vendor.done', $token);
    }

    public function done(Request $request, string $token)
    {
        $invitation = Invitation::findByToken($token);
        abort_unless($invitation && $this->verified($request, $invitation) && $invitation->response?->isSubmitted(), 404);
        $version = $invitation->version;

        return Inertia::render('Vendor/Done', [
            'questionnaire' => ['name' => $version->questionnaire->name, 'structure' => $version->structure_json],
            'vendor' => (object) $invitation->response->vendor_json,
            'answers' => (object) $invitation->response->answers_json,
            'submittedAt' => $invitation->response->submitted_at->toIso8601String(),
        ]);
    }

    /** Render a clear message for invalid, revoked, expired or already-submitted links. */
    private function closed(?Invitation $invitation, Request $request)
    {
        $reason = match (true) {
            $invitation === null => 'invalid',
            $invitation->status === 'revoked' => 'revoked',
            in_array($invitation->status, ['submitted', 'scored', 'decided'], true) => 'submitted',
            $invitation->isExpired() => 'expired',
            default => null,
        };
        if ($reason === null) {
            return null;
        }
        if ($reason === 'submitted' && $this->verified($request, $invitation)) {
            return redirect()->route('vendor.done', $request->route('token'));
        }
        if ($reason === 'expired' && $invitation->status !== 'expired') {
            $invitation->update(['status' => 'expired']);
        }

        return Inertia::render('Vendor/Closed', ['reason' => $reason])->toResponse($request)->setStatusCode($reason === 'invalid' ? 404 : 410);
    }

    private function verified(Request $request, ?Invitation $invitation): bool
    {
        return $invitation && $request->session()->has('vendor_invitations.'.$invitation->id);
    }

    /** Token + OTP session for write endpoints; rejects closed invitations. */
    private function authorised(Request $request, string $token): Invitation
    {
        $invitation = Invitation::findByToken($token);
        abort_unless($invitation && $this->verified($request, $invitation), 403);
        abort_unless($invitation->acceptsAnswers(), 410, 'This link has expired or been closed.');

        return $invitation;
    }

    /** Keep only known fields and question ids, typed and length-limited. */
    private function clean(Invitation $invitation, Request $request): array
    {
        $vendor = [];
        foreach (self::VENDOR_FIELDS as $key => $max) {
            $v = $request->input("vendor.$key");
            if (is_scalar($v) && trim((string) $v) !== '') {
                $vendor[$key] = mb_substr(trim((string) $v), 0, $max);
            }
        }

        $answers = [];
        foreach (ScoringEngine::questions($invitation->version->structure_json) as $q) {
            $a = $request->input('answers.'.$q['id']);
            if (! is_scalar($a) || trim((string) $a) === '') {
                continue;
            }
            $a = trim((string) $a);
            $answers[$q['id']] = match ($q['type']) {
                'yesno' => in_array($a, ['yes', 'no'], true) ? $a : null,
                'choice' => in_array($a, $q['options'] ?? [], true) ? $a : null,
                'number' => is_numeric($a) ? $a + 0 : mb_substr($a, 0, 30),
                'text' => mb_substr($a, 0, self::TEXT_MAX),
                default => null,
            };
            if ($answers[$q['id']] === null) {
                unset($answers[$q['id']]);
            }
        }

        return [$vendor, $answers];
    }

    private function validateComplete(Invitation $invitation, array $vendor, array $answers, bool $declaration): void
    {
        $errors = [];
        foreach (['company' => 'Company name', 'pan' => 'PAN/VAT number', 'contact_person' => 'Contact person', 'email' => 'Email', 'phone' => 'Phone'] as $key => $label) {
            if (empty($vendor[$key])) {
                $errors["vendor.$key"] = "$label is required.";
            }
        }
        if (! empty($vendor['email']) && ! filter_var($vendor['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['vendor.email'] = 'Enter a valid email address.';
        }
        foreach (ScoringEngine::questions($invitation->version->structure_json) as $q) {
            $a = $answers[$q['id']] ?? null;
            if ($a === null && ($q['required'] ?? true)) {
                $errors['answers.'.$q['id']] = 'This question is required.';
            } elseif ($q['type'] === 'number' && $a !== null && ! is_int($a) && ! is_float($a)) {
                $errors['answers.'.$q['id']] = 'Enter a number.';
            }
        }
        if (! $declaration) {
            $errors['declaration'] = 'Please confirm the declaration.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email) + [1 => ''];

        return mb_substr($local, 0, 2).str_repeat('•', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }
}
