<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Audit log viewer (ADM-07, `audit.view`): read-only, newest first, filtered by action, staff
 * member, subject and date. The log is append-only; IP addresses exist only as HMAC hashes and are
 * never shown.
 */
#[Layout('layouts.admin')]
#[Title('Audit log')]
class AuditLogViewer extends Component
{
    use RequiresPermission, WithPagination;

    /** Action or its prefix: `listing.` matches every listing action. */
    #[Url]
    public string $action = '';

    /** Staff email (exact) or user id. */
    #[Url]
    public string $actor = '';

    #[Url]
    public string $subjectType = '';

    #[Url]
    public string $subjectId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::AUDIT_VIEW);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset(['action', 'actor', 'subjectType', 'subjectId', 'from', 'to']);
        $this->resetPage();
    }

    public function render(): View
    {
        $this->requirePermission(Permissions::AUDIT_VIEW);
        $actorId = $this->actorId();
        $from = $this->date($this->from);
        $to = $this->date($this->to);

        return view('livewire.admin.audit', [
            'entries' => AuditLog::query()
                ->with('actor:id,name,email')
                ->when($this->action !== '', fn ($q) => str_ends_with($this->action, '.')
                    ? $q->where('action', 'like', addcslashes($this->action, '%_\\').'%')
                    : $q->where('action', $this->action))
                ->when($this->actor !== '', fn ($q) => $q->where('actor_id', $actorId ?? 0))
                ->when($this->subjectType !== '', fn ($q) => $q->where('subject_type', $this->subjectType))
                ->when(ctype_digit($this->subjectId), fn ($q) => $q->where('subject_id', (int) $this->subjectId))
                ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from.' 00:00:00'))
                ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to.' 23:59:59'))
                ->orderByDesc('id')
                ->paginate(50),
            'subjectTypes' => AuditLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }

    private function actorId(): ?int
    {
        $actor = trim($this->actor);
        if ($actor === '') {
            return null;
        }

        return ctype_digit($actor) ? (int) $actor : User::withTrashed()->where('email', mb_strtolower($actor))->value('id');
    }

    private function date(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }
}
