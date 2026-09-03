<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Services\GradeCalculationService;
use Modules\Class\Models\AcademicClass;
use Modules\Student\Models\Student;
use Tests\TestCase;

class GradeCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected GradeCalculationService $calcService;
    protected AcademicClass $academicClass;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->calcService = app(GradeCalculationService::class);
        $this->academicClass = AcademicClass::first();
        $this->student = Student::first();
    }

    public function test_converts_score_to_letter_grade_and_point_correctly(): void
    {
        $gradeA = $this->calcService->convertScoreToGrade(88.50);
        $this->assertEquals('A', $gradeA['letter_grade']);
        $this->assertEquals(4.00, $gradeA['grade_point']);

        $gradeBPlus = $this->calcService->convertScoreToGrade(78.00);
        $this->assertEquals('B+', $gradeBPlus['letter_grade']);
        $this->assertEquals(3.50, $gradeBPlus['grade_point']);

        $gradeB = $this->calcService->convertScoreToGrade(68.00);
        $this->assertEquals('B', $gradeB['letter_grade']);
        $this->assertEquals(3.00, $gradeB['grade_point']);

        $gradeC = $this->calcService->convertScoreToGrade(58.00);
        $this->assertEquals('C', $gradeC['letter_grade']);
        $this->assertEquals(2.00, $gradeC['grade_point']);

        $gradeD = $this->calcService->convertScoreToGrade(45.00);
        $this->assertEquals('D', $gradeD['letter_grade']);
        $this->assertEquals(1.00, $gradeD['grade_point']);

        $gradeE = $this->calcService->convertScoreToGrade(25.00);
        $this->assertEquals('E', $gradeE['letter_grade']);
        $this->assertEquals(0.00, $gradeE['grade_point']);
    }

    public function test_calculates_student_final_score_with_weights(): void
    {
        $result = $this->calcService->calculateStudentFinalScore($this->student, $this->academicClass);

        $this->assertArrayHasKey('final_score', $result);
        $this->assertArrayHasKey('letter_grade', $result);
        $this->assertArrayHasKey('grade_point', $result);
        $this->assertArrayHasKey('components_breakdown', $result);

        $this->assertGreaterThanOrEqual(0, $result['final_score']);
        $this->assertLessThanOrEqual(100, $result['final_score']);
    }
}
