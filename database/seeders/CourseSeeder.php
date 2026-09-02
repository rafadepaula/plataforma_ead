<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\YoutubeSanitizerService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CourseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Path, on the `public` disk, of the support PDF attached to the
     * safety module's lesson.
     */
    private const PDF_PATH = 'courses/docs/apostila-seguranca-eletricista.pdf';

    /**
     * Seed the single development course of "Liga Certo": "Curso de
     * Eletricista" with three modules (text, PDF and video), a quiz on
     * each, the student enrollment and the completion rules — every
     * lesson completed (100%) plus a passing 70% score on the final quiz.
     */
    public function run(): void
    {
        $ligaCerto = Organization::where('slug', 'liga-certo')->first();

        if (! $ligaCerto) {
            $this->call(OrganizationSeeder::class);
            $ligaCerto = Organization::where('slug', 'liga-certo')->first();
        }

        $aluno = User::where('email', 'aluno.ligacerto@plataforma.com')->first();

        if (! $aluno) {
            $this->call(UserSeeder::class);
            $aluno = User::where('email', 'aluno.ligacerto@plataforma.com')->first();
        }

        $this->ensurePdfAttachment();

        $course = Course::withoutGlobalScopes()->firstOrCreate(
            ['org_id' => $ligaCerto?->id, 'title' => 'Curso de Eletricista'],
            [
                'description' => 'Formação prática em instalações elétricas residenciais: fundamentos, normas de segurança e montagem de circuitos.',
                'workload_hours' => 40,
                'is_published' => true,
            ]
        );

        $this->seedFundamentalsModule($course);
        $this->seedSafetyModule($course);
        $finalQuiz = $this->seedPracticeModule($course);

        $this->seedCompletionRules($course, $finalQuiz);

        if ($aluno) {
            $course->students()->syncWithoutDetaching([
                $aluno->id => ['enrolled_at' => now(), 'status' => 'active', 'progress_percentage' => 0],
            ]);
        }
    }

    /**
     * Module 1 — text lesson plus a quiz whose single question is an
     * essay one (manually graded by the organizer).
     */
    private function seedFundamentalsModule(Course $course): Quiz
    {
        $module = Module::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Fundamentos de Eletricidade'],
            [
                'description' => 'Tensão, corrente, resistência e a Lei de Ohm.',
                'order_index' => 1,
            ]
        );

        Lesson::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Tensão, Corrente e Resistência'],
            [
                'type' => 'content',
                'content_text' => 'Nesta aula você vai entender os três grandezas básicas da eletricidade: a tensão (diferença de potencial medida em volts), a corrente (fluxo de elétrons medido em ampères) e a resistência (oposição à passagem da corrente, medida em ohms). A relação entre elas é descrita pela Primeira Lei de Ohm: V = R × I.',
                'order_index' => 1,
                'is_published' => true,
            ]
        );

        $quizLesson = Lesson::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Avaliação — Fundamentos de Eletricidade'],
            [
                'type' => 'quiz',
                'content_text' => null,
                'order_index' => 2,
                'is_published' => true,
            ]
        );

        $quiz = $this->quizFor(
            $quizLesson,
            'Quiz — Fundamentos de Eletricidade',
            'Responda com suas palavras. Esta questão é dissertativa e será corrigida pelo organizador do curso.'
        );

        $this->essayQuestion(
            $quiz,
            1,
            'Explique, com suas palavras, a relação entre tensão, corrente e resistência em um circuito elétrico simples e cite a lei que descreve essa relação.'
        );

        return $quiz;
    }

    /**
     * Module 2 — PDF handout lesson plus an auto-graded quiz.
     */
    private function seedSafetyModule(Course $course): Quiz
    {
        $module = Module::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Segurança e Normas'],
            [
                'description' => 'EPIs, EPCs e a NR-10 na rotina do eletricista.',
                'order_index' => 2,
            ]
        );

        Lesson::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Apostila — Segurança em Instalações Elétricas'],
            [
                'type' => 'content',
                'content_text' => 'Estude a apostila em anexo antes de responder a avaliação do módulo.',
                'pdf_path' => self::PDF_PATH,
                'order_index' => 1,
                'is_published' => true,
            ]
        );

        $quizLesson = Lesson::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Avaliação — Segurança e Normas'],
            [
                'type' => 'quiz',
                'content_text' => null,
                'order_index' => 2,
                'is_published' => true,
            ]
        );

        $quiz = $this->quizFor(
            $quizLesson,
            'Quiz — Segurança e Normas',
            'Leia atentamente cada questão antes de responder.'
        );

        $this->choiceQuestion(
            $quiz,
            1,
            'O que a NR-10 estabelece?',
            'Diretrizes de segurança em instalações e serviços com eletricidade',
            [
                'Regras de trânsito para veículos elétricos',
                'Padrões de acabamento para quadros de distribuição',
            ]
        );

        $this->trueFalseQuestion(
            $quiz,
            2,
            'É seguro realizar manutenção em um circuito energizado, desde que se use luvas comuns de borracha.',
            false
        );

        return $quiz;
    }

    /**
     * Module 3 — video lesson plus the final auto-graded quiz that gates
     * course completion.
     */
    private function seedPracticeModule(Course $course): Quiz
    {
        $module = Module::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Instalações Elétricas na Prática'],
            [
                'description' => 'Montagem de um circuito residencial passo a passo.',
                'order_index' => 3,
            ]
        );

        Lesson::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Videoaula — Circuito Residencial Passo a Passo'],
            [
                'type' => 'content',
                'content_text' => 'Assista à videoaula completa antes de realizar a avaliação final.',
                'video_provider' => 'youtube',
                'video_url' => app(YoutubeSanitizerService::class)->sanitize('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
                'order_index' => 1,
                'is_published' => true,
            ]
        );

        $quizLesson = Lesson::firstOrCreate(
            ['module_id' => $module->id, 'title' => 'Avaliação Final'],
            [
                'type' => 'quiz',
                'content_text' => null,
                'order_index' => 2,
                'is_published' => true,
            ]
        );

        $quiz = $this->quizFor(
            $quizLesson,
            'Avaliação Final do Curso',
            'A nota mínima para aprovação é 70%. Você precisa assistir à videoaula e concluir todas as aulas para concluir o curso.'
        );

        $this->choiceQuestion(
            $quiz,
            1,
            'Em um circuito de 127 V percorrido por uma corrente de 10 A, qual é a potência aproximada consumida?',
            '1.270 W',
            ['127 W', '12.700 W']
        );

        $this->trueFalseQuestion(
            $quiz,
            2,
            'O disjuntor deve ser dimensionado de acordo com a corrente suportada pelos condutores do circuito.',
            true
        );

        return $quiz;
    }

    /**
     * The course completion rules: every published lesson completed
     * (100%, including the video) AND a 70% minimum score on the final
     * quiz — both must hold.
     */
    private function seedCompletionRules(Course $course, Quiz $finalQuiz): void
    {
        CourseCompletionRule::firstOrCreate(
            ['course_id' => $course->id, 'rule_type' => 'all_lessons'],
            ['target_id' => null, 'required_percentage' => 100]
        );

        CourseCompletionRule::firstOrCreate(
            ['course_id' => $course->id, 'rule_type' => 'min_quiz_score', 'target_id' => $finalQuiz->id],
            ['required_percentage' => 70]
        );
    }

    private function quizFor(Lesson $quizLesson, string $title, string $instructions): Quiz
    {
        return Quiz::firstOrCreate(
            ['lesson_id' => $quizLesson->id],
            [
                'title' => $title,
                'instructions' => $instructions,
                'allow_retries' => true,
                'max_attempts' => 3,
                'time_limit_minutes' => null,
                'show_correct_answers' => true,
                'min_score_percentage' => 70,
            ]
        );
    }

    private function essayQuestion(Quiz $quiz, int $order, string $text): QuizQuestion
    {
        return QuizQuestion::firstOrCreate(
            ['quiz_id' => $quiz->id, 'question_text' => $text],
            [
                'type' => 'essay',
                'order_index' => $order,
            ]
        );
    }

    /**
     * @param  list<string>  $wrongOptions
     */
    private function choiceQuestion(Quiz $quiz, int $order, string $text, string $correctOption, array $wrongOptions): QuizQuestion
    {
        $question = QuizQuestion::firstOrCreate(
            ['quiz_id' => $quiz->id, 'question_text' => $text],
            [
                'type' => 'single_choice',
                'order_index' => $order,
            ]
        );

        $this->option($question, $correctOption, true);
        foreach ($wrongOptions as $wrongOption) {
            $this->option($question, $wrongOption, false);
        }

        return $question;
    }

    private function trueFalseQuestion(Quiz $quiz, int $order, string $text, bool $correctIsTrue): QuizQuestion
    {
        $question = QuizQuestion::firstOrCreate(
            ['quiz_id' => $quiz->id, 'question_text' => $text],
            [
                'type' => 'true_false',
                'order_index' => $order,
            ]
        );

        $this->option($question, 'Verdadeiro', $correctIsTrue);
        $this->option($question, 'Falso', ! $correctIsTrue);

        return $question;
    }

    private function option(QuizQuestion $question, string $text, bool $isCorrect): QuizOption
    {
        return QuizOption::firstOrCreate(
            ['question_id' => $question->id, 'option_text' => $text],
            ['is_correct' => $isCorrect]
        );
    }

    /**
     * Writes the module 2 handout to the `public` disk once — the
     * classroom PDF viewer links to it through `/storage`.
     */
    private function ensurePdfAttachment(): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists(self::PDF_PATH)) {
            $disk->put(self::PDF_PATH, $this->pdfAttachment());
        }
    }

    /**
     * Builds a minimal but valid single-page PDF document so the seeded
     * lesson has a real file to render, without shipping a binary in the
     * repository.
     */
    private function pdfAttachment(): string
    {
        $lines = [
            'Apostila — Segurança em Instalações Elétricas',
            'Curso de Eletricista — Liga Certo',
            '',
            '1. Desligue a energia antes de qualquer intervenção e confirme a ausência de tensão.',
            '2. Use sempre os EPIs adequados: luvas isolantes, óculos de proteção e botas.',
            '3. Sinalize e bloqueie a área de trabalho conforme a NR-10.',
            '4. Dimensione condutores e disjuntores de acordo com a corrente do circuito.',
            '5. Aterre equipamentos e estruturas condutoras para evitar choques.',
            '6. Em caso de acidente, acione o socorro e nunca toque na vítima energizada.',
        ];

        $content = 'BT /F1 16 Tf 56 780 Td ('.$this->pdfText($lines[0]).") Tj ET\n";
        $content .= 'BT /F1 11 Tf 56 760 Td ('.$this->pdfText($lines[1]).") Tj ET\n";

        $y = 720;
        foreach (array_slice($lines, 2) as $line) {
            $content .= 'BT /F1 12 Tf 56 '.$y.' Td ('.$this->pdfText($line).") Tj ET\n";
            $y -= 24;
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref\n0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= 'trailer'.PHP_EOL.'<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'.PHP_EOL;
        $pdf .= 'startxref'.PHP_EOL.$xrefOffset.PHP_EOL.'%%EOF'.PHP_EOL;

        return $pdf;
    }

    /**
     * Encodes a UTF-8 line as WinAnsi (Windows-1252, which — unlike
     * Latin-1 — maps the em dash) escaped for a PDF literal string.
     */
    private function pdfText(string $value): string
    {
        $winAnsi = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $winAnsi);
    }
}
