<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\Offerings\CourseStageGenerator;
use Illuminate\Console\Command;

class BackfillCourseStages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course-stages:backfill
        {--course= : Only backfill the course with this id}
        {--chunk=100 : Courses to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the missing progression stages for existing courses';

    /**
     * Execute the console command.
     */
    public function handle(CourseStageGenerator $generator): int
    {
        $query = Course::query()->orderBy('id');

        if ($courseId = $this->option('course')) {
            $query->whereKey($courseId);
        }

        $courses = 0;
        $created = 0;

        $query->chunkById((int) $this->option('chunk'), function ($chunk) use ($generator, &$courses, &$created): void {
            foreach ($chunk as $course) {
                $courses++;
                $created += $generator->generate($course);
            }
        });

        $this->info("Processed {$courses} course(s); created {$created} stage(s).");

        return self::SUCCESS;
    }
}
