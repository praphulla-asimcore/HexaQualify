<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Questionnaire;
use App\Models\Setting;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function index(Request $request)
    {
        $status = $request->input('status');
        $search = trim((string) $request->input('q'));

        $query = Invitation::query()
            ->with(['version.questionnaire', 'creator', 'response:id,invitation_id,submitted_at'])
            ->latest();
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(vendor_company) like ?', [$like])->orWhereRaw('lower(contact_email) like ?', [$like]));
        }

        $rows = $query->get()->map(fn (Invitation $i) => [
            'id' => $i->id,
            'vendor_company' => $i->vendor_company,
            'contact_name' => $i->contact_name,
            'contact_email' => $i->contact_email,
            'questionnaire' => $i->version->questionnaire->name,
            'version' => $i->version->version,
            'status' => $i->effectiveStatus(),
            'expires_at' => $i->expires_at->toIso8601String(),
            'sent_at' => $i->sent_at?->toIso8601String(),
            'opened_at' => $i->opened_at?->toIso8601String(),
            'created_by' => $i->creator?->name,
            'note' => $i->note,
            'notify_result' => $i->notify_result,
            'response_id' => $i->response?->submitted_at ? $i->response->id : null,
        ]);
        if ($status) {
            $rows = $rows->where('status', $status)->values();
        }

        return Inertia::render('Invitations/Index', [
            'rows' => $rows,
            'filters' => ['status' => $status, 'q' => $search],
            'questionnaires' => Questionnaire::where('active', true)->has('versions')->orderBy('sort_order')->get(['id', 'name', 'category']),
            'defaultExpiry' => (int) Setting::get('default_expiry_days'),
            'statuses' => Invitation::STATUSES,
            'can' => ['manage' => $request->user()->canReview()],
        ]);
    }

    /** Duplicate guard: how many open invitations already exist for this email + questionnaire. */
    public function check(Request $request)
    {
        $data = $request->validate(['contact_email' => ['required', 'email'], 'questionnaire_id' => ['required', 'uuid']]);
        $q = Questionnaire::findOrFail($data['questionnaire_id']);

        return response()->json(['open' => $this->invitations->duplicates($data['contact_email'], $q)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_company' => ['required', 'string', 'max:200'],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email:strict', 'max:200'],
            'questionnaire_id' => ['required', 'uuid', Rule::exists('questionnaires', 'id')->where('active', true)->whereNull('deleted_at')],
            'expiry_days' => ['required', 'integer', 'min:1', 'max:90'],
            'note' => ['nullable', 'string', 'max:2000'],
            'notify_result' => ['boolean'],
            'confirm_duplicate' => ['boolean'],
        ]);
        $questionnaire = Questionnaire::with('latestVersion')->findOrFail($data['questionnaire_id']);

        if (! ($data['confirm_duplicate'] ?? false) && $this->invitations->duplicates($data['contact_email'], $questionnaire) > 0) {
            return redirect()->route('invitations.index')->withInput()->withErrors(['contact_email' => 'This vendor email already has an open invitation for this questionnaire. Tick "Send anyway" to continue.'])
                ->with('duplicate', true);
        }

        [, $plain] = $this->invitations->create($data, $questionnaire, $request->user());

        return redirect()->route('invitations.index')->with('success', 'Invitation sent to '.$data['contact_email'].'.')->with('link', InvitationService::link($plain));
    }

    public function resend(Invitation $invitation)
    {
        abort_unless($invitation->acceptsAnswers(), 422, 'Only open invitations can be resent. Extend it first if it has expired.');
        $plain = $this->invitations->resend($invitation);

        return back()->with('success', 'Invitation resent with a new link. The previous link no longer works.')
            ->with('link', InvitationService::link($plain));
    }

    public function extend(Request $request, Invitation $invitation)
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']]);
        abort_if(in_array($invitation->status, ['revoked', 'submitted', 'scored', 'decided'], true), 422);
        $this->invitations->extend($invitation, $data['days']);

        return back()->with('success', 'Expiry extended to '.$invitation->expires_at->timezone('Asia/Kathmandu')->format('j M Y').'.');
    }

    public function revoke(Invitation $invitation)
    {
        abort_if(in_array($invitation->status, ['submitted', 'scored', 'decided', 'revoked'], true), 422);
        $this->invitations->revoke($invitation);

        return back()->with('success', 'Invitation revoked. The link no longer works.');
    }
}
