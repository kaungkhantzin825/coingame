<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use App\Services\LedgerService;
use App\Services\TaskService;
use Illuminate\Http\Request;

/**
 * Tasks list + claim. Progress is written by TaskService when the server
 * processes game events, so `progress` here is always trustworthy.
 */
class TaskController extends ApiController
{
    public function __construct(
        private LedgerService $ledger,
        private TaskService $tasks,
    ) {
    }

    /** GET /api/tasks */
    public function index(Request $request)
    {
        $user = $this->user($request);

        $tasks = Task::where('active', true)->orderBy('sort')->get()->map(function (Task $task) use ($user) {
            $row = $this->tasks->row($user, $task);

            return [
                'key' => $task->key,
                'title' => $task->title,
                'description' => $task->description,
                'kind' => $task->kind,
                'icon' => $task->icon,
                'goal' => (int) $task->goal,
                'progress' => (int) $row->progress,
                'completed' => $row->completed_at !== null,
                'claimed' => $row->claimed_at !== null,
                'claimable' => $row->completed_at !== null && $row->claimed_at === null,
                'reward' => [
                    'coins' => (int) $task->reward_coins,
                    'cash_cents' => (int) $task->reward_cash_cents,
                    'gems' => (int) $task->reward_gems,
                ],
            ];
        });

        return $this->ok(['tasks' => $tasks]);
    }

    /** POST /api/tasks/{key}/claim */
    public function claim(Request $request, string $key)
    {
        $user = $this->user($request);

        $task = Task::where('key', $key)->where('active', true)->first();

        if (! $task) {
            return $this->fail('Unknown task.', 404);
        }

        $row = $this->tasks->row($user, $task);

        if ($row->claimed_at !== null) {
            return $this->fail('Already claimed.', 409);
        }

        if ($row->completed_at === null) {
            return $this->fail('Task is not finished yet.', 422, [
                'progress' => (int) $row->progress,
                'goal' => (int) $task->goal,
            ]);
        }

        $period = $task->kind === 'daily' ? now()->toDateString() : 'once';

        $this->ledger->apply(
            $user,
            'task_reward',
            [
                'coins' => (int) $task->reward_coins,
                'cash_cents' => (int) $task->reward_cash_cents,
                'gems' => (int) $task->reward_gems,
            ],
            idempotencyKey: "task:{$user->id}:{$task->id}:{$period}",
            source: 'task',
            meta: ['task' => $task->key],
        );

        $row->forceFill(['claimed_at' => now()])->save();

        return $this->ok([
            'task' => $task->key,
            'reward' => [
                'coins' => (int) $task->reward_coins,
                'cash_cents' => (int) $task->reward_cash_cents,
                'gems' => (int) $task->reward_gems,
            ],
            'wallet' => $user->fresh()->wallet(),
        ]);
    }
}
