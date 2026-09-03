<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $modulesPath = base_path('modules');

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $module) {
            $moduleName = basename($module);
            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";

            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Explicit route model bindings for modular entities where route param differs from class basename
        Route::model('class', \Modules\Class\Models\AcademicClass::class);
        Route::model('academic_class', \Modules\Class\Models\AcademicClass::class);
        Route::model('schedule', \Modules\Schedule\Models\ClassSchedule::class);
        Route::model('class_schedule', \Modules\Schedule\Models\ClassSchedule::class);
        Route::model('enrollment', \Modules\Enrollment\Models\StudentEnrollment::class);
        Route::model('student_enrollment', \Modules\Enrollment\Models\StudentEnrollment::class);
        Route::model('advisor', \Modules\Advising\Models\AcademicAdvisor::class);
        Route::model('academic_advisor', \Modules\Advising\Models\AcademicAdvisor::class);
        Route::model('session', \Modules\Advising\Models\AdvisingSession::class);
        Route::model('advising_session', \Modules\Advising\Models\AdvisingSession::class);
        Route::model('teaching_session', \Modules\Attendance\Models\TeachingSession::class);
        Route::model('scheme', \Modules\Assessment\Models\AssessmentScheme::class);
        Route::model('assessment_scheme', \Modules\Assessment\Models\AssessmentScheme::class);
        Route::model('component', \Modules\Assessment\Models\AssessmentComponent::class);
        Route::model('assessment_component', \Modules\Assessment\Models\AssessmentComponent::class);
        Route::model('grade', \Modules\Assessment\Models\StudentGrade::class);
        Route::model('student_grade', \Modules\Assessment\Models\StudentGrade::class);

        $modulesPath = base_path('modules');

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $module) {
            $moduleName = basename($module);

            // 1. Register module routes
            $apiRoutes = $module . '/Routes/api.php';
            if (File::exists($apiRoutes)) {
                Route::prefix('api/v1')
                    ->middleware('api')
                    ->group($apiRoutes);
            }

            // 2. Register module migrations
            $migrations = $module . '/Database/Migrations';
            if (File::isDirectory($migrations)) {
                $this->loadMigrationsFrom($migrations);
            }
        }
    }
}
