<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ProjectTask;
use App\Models\TaskLift;
use Illuminate\Database\Seeder;

/**
 * Demo lifts on structural tasks: Lift 1 poured + passed, Lift 2 poured awaiting
 * inspection, Lift 3 planned — so the inspection hold point is visible.
 */
class LiftsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company || TaskLift::where('company_id', $company->id)->exists()) {
            return;
        }

        // Pick a few structural/foundation tasks to attach lifts to.
        $tasks = ProjectTask::where('company_id', $company->id)
            ->whereIn('phase', ['Structure', 'Foundation'])
            ->orderBy('id')->take(3)->get();

        foreach ($tasks as $task) {
            $lifts = [
                ['seq' => 1, 'status' => 'passed', 'planned' => 12, 'poured' => 12.5, 'result' => 'pass', 'note' => 'کیفیت خوب، بدون درز سرد.'],
                ['seq' => 2, 'status' => 'poured', 'planned' => 12, 'poured' => 11.8, 'result' => null, 'note' => null],
                ['seq' => 3, 'status' => 'planned', 'planned' => 12, 'poured' => null, 'result' => null, 'note' => null],
            ];
            foreach ($lifts as $l) {
                TaskLift::create([
                    'company_id' => $company->id, 'project_id' => $task->project_id, 'task_id' => $task->id,
                    'seq' => $l['seq'], 'lift_type' => 'concrete',
                    'description' => 'ریختن کانکریت — لیفت '.$l['seq'],
                    'unit' => 'm3', 'planned_qty' => $l['planned'], 'poured_qty' => $l['poured'], 'height_m' => 1.5,
                    'pour_date' => $l['status'] === 'planned' ? null : now()->subDays(6 - $l['seq'] * 2)->toDateString(),
                    'status' => $l['status'],
                    'inspection_result' => $l['result'],
                    'inspected_by' => $l['result'] ? 'انجنیر احمد (انجنیر ساحه)' : null,
                    'inspected_at' => $l['result'] ? now()->subDays(4) : null,
                    'inspection_note' => $l['note'],
                ]);
            }
        }
    }
}
