<?php

namespace App\Http\Livewire;

use App\Models\Member;
use Carbon\Carbon;
use Livewire\Component;

class DashboardMemberStats extends Component
{
    public string $tab = 'entries';

    public $entries = [];
    public $exits = [];
    public $birthdays = [];
    public $anniversaries = [];

    public function mount(): void
    {
        $tenantId = auth()->user()->tenant_id;
        $today = Carbon::today();
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();

        $this->entries = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('entry_date')
            ->whereBetween('entry_date', [$yearStart, $yearEnd])
            ->orderBy('entry_date', 'desc')
            ->limit(5)
            ->get();

        $this->exits = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('exit_date')
            ->whereBetween('exit_date', [$yearStart, $yearEnd])
            ->orderBy('exit_date', 'desc')
            ->limit(5)
            ->get();

        $this->birthdays = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('birthday')
            ->get()
            ->map(function ($member) use ($today) {
                $nextBirthday = Carbon::parse($member->birthday)->year($today->year);

                if ($nextBirthday->lt($today)) {
                    return null;
                }

                $member->next_birthday_date = $nextBirthday;
                $member->next_birthday_age = $today->year - $member->birthday->year;

                return $member;
            })
            ->filter()
            ->sortBy('next_birthday_date')
            ->take(5);

        $this->anniversaries = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('entry_date')
            ->get()
            ->map(function ($member) use ($today, $yearEnd) {
                $entryDate = Carbon::parse($member->entry_date);
                $anniversaryDate = $entryDate->copy()->year($today->year);

                if ($anniversaryDate->lt($today) || $anniversaryDate->gt($yearEnd)) {
                    return null;
                }

                $years = $today->year - $entryDate->year;

                if ($years <= 0 || $years % 5 !== 0) {
                    return null;
                }

                $member->anniversary_date = $anniversaryDate;
                $member->anniversary_years = $years;

                return $member;
            })
            ->filter()
            ->sortBy('anniversary_date')
            ->take(5);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['entries', 'exits', 'birthdays', 'anniversaries'], true)) {
            $this->tab = $tab;
        }
    }

    public function render()
    {
        return view('livewire.dashboard-member-stats');
    }
}
