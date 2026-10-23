<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Models\UserTask;

/**
 * Tracks per-user progress against `tasks`. Progress is advanced by server-side
 * game events (see GameController), never by the client claiming a number.
 */
class TaskService
{
    /** Add to the progress of every active task listening on `$event`. */
    public function record(User $user, string $event, int $amount = 1): void
    {
        if ($amount <= 0) {
            return;
        }

        foreach ($this->tasksFor($event) as $task) {
            $row = $this->row($user, $task);
            $this->advance($row, $task, (int) $row->progress + $amount);
        }
    }

    /** For "best in a single run" goals: keep the highest value, do not sum. */
    public function recordMax(User $user, string $event, int $value): void
    {
        if ($value <= 0) {
            return;
        }

        foreach ($this->tasksFor($event) as $task) {
            $row = $this->row($user, $task);

            if ($value > (int) $row->progress) {
                $this->advance($row, $task, $value);
            }
        }
    }

    /** The user's row for the current period of a task. */
    public function row(User $user, Task $task): UserTask
    {
        $period = $this->period($task);

        // `period` is cast to a date, so it is stored as a full datetime string.
        // Match it with whereDate() — a plain where('period', 'Y-m-d') misses the
        // existing row and trips the unique index.
        $existing = UserTask::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->when(
                $period === null,
                fn ($q) => $q->whereNull('period'),
                fn ($q) => $q->whereDate('period', $period),
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        return UserTask::create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'period' => $period,
            'progress' => 0,
        ]);
    }

    private function tasksFor(string $event)
    {
        return Task::where('active', true)->where('event', $event)->get();
    }

    private function advance(UserTask $row, Task $task, int $progress): void
    {
        $row->progress = min((int) $task->goal, $progress);

        if ((int) $row->progress >= (int) $task->goal && $row->completed_at === null) {
            $row->completed_at = now();
        }

        $row->save();
    }

    /** Daily tasks reset each day; everything else lives forever (period null). */
    private function period(Task $task): ?string
    {
        return $task->kind === 'daily' ? now()->toDateString() : null;
    }
}
