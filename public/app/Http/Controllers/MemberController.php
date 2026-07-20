<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\CustomMemberField;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Services\MemberService;
use App\Services\MembershipService;

class MemberController extends Controller
{
    public function __construct(
        protected MemberService $memberService
    ) {}

    /**
     * Mitgliederübersicht
     */
    public function index()
    {
        $tenantId = app('currentTenant')->id;

        $query = Member::where('tenant_id', $tenantId);

        if (request()->filled('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('member_id', 'like', "%{$search}%");
            });
        }

        $sortField = request('sort', 'last_name');
        $sortDirection = request('direction', 'asc');
        $allowedFields = ['first_name', 'last_name', 'email', 'member_id', 'entry_date'];

        if (!in_array($sortField, $allowedFields)) {
            $sortField = 'last_name';
        }

        $members = $query->orderBy($sortField, $sortDirection)
                         ->paginate(25)
                         ->appends(request()->query());

        return view('members.index', compact('members', 'sortField', 'sortDirection'));
    }

    /**
     * Neues Mitglied – Formular
     */
    public function create(MembershipService $membershipService)
    {
        $memberships = $membershipService->getForTenant();
        return view('members.create', compact('memberships'));
    }

    /**
     * Neues Mitglied speichern
     */
    public function store(StoreMemberRequest $request)
    {
        $this->memberService->create($request);
        return redirect()->route('members.index')->with('success', 'Mitglied erfolgreich hinzugefügt.');
    }

    /**
     * Einzelansicht Mitglied
     */
    public function show(Member $member)
    {
        $this->authorizeMember($member);
        $member->load('customValues');

        $customFields = CustomMemberField::where('tenant_id', $member->tenant_id)
            ->where('visible', true)
            ->orderBy('order')
            ->get();

        return view('members.show', compact('member', 'customFields'));
    }

    /**
     * Mitglied bearbeiten – Formular
     */
    public function edit(Member $member, MembershipService $membershipService)
    {
        $this->authorizeMember($member);
        $memberships = $membershipService->getForTenant();
        $member->load('customValues');

        $customFields = CustomMemberField::where('tenant_id', $member->tenant_id)
            ->where('visible', true)
            ->orderBy('order')
            ->get();

        return view('members.edit', compact('member', 'memberships', 'customFields'));
    }

    /**
     * Mitglied aktualisieren
     */
    public function update(UpdateMemberRequest $request, Member $member)
    {
        $this->authorizeMember($member);
        $this->memberService->update($request, $member);
        return redirect()->route('members.index')->with('success', 'Mitglied erfolgreich aktualisiert.');
    }

    /**
     * Mitglied löschen
     */
    public function destroy(Member $member)
    {
        $this->authorizeMember($member);

        if ($member->photo && Storage::disk('public')->exists($member->photo)) {
            Storage::disk('public')->delete($member->photo);
        }

        $member->delete();
        return redirect()->route('members.index')->with('success', 'Mitglied gelöscht.');
    }

    /**
     * DSGVO-Datenauskunft als PDF
     */
    public function exportDatenauskunft(Member $member)
    {
        $this->authorizeMember($member);

        $pdf = Pdf::loadView('members.pdf.datenauskunft', ['member' => $member]);
        return $pdf->download("Datenauskunft_{$member->last_name}_{$member->id}.pdf");
    }

    /**
     * Zugriffsschutz – Mitglied gehört zu Tenant?
     */
    private function authorizeMember(Member $member): void
    {
        abort_if($member->tenant_id !== app('currentTenant')->id, 403, 'Unberechtigter Zugriff.');
    }
}
