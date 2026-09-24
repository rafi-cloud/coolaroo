<?php

namespace App\Console\Commands;

use App\Exceptions\AiUnavailableException;
use App\Services\AiMenuService;
use Illuminate\Console\Command;

class WarmAiCache extends Command
{
    protected $signature = 'ai:warm';

    protected $description = 'Prime the AI cache with the chat starter questions and common meal-builder briefs';

    /**
     * @var array<int, array{budget:int, party_size:int, dietary:array<int,string>, preferences:?string}>
     */
    private const BRIEFS = [
        ['budget' => 60, 'party_size' => 2, 'dietary' => [], 'preferences' => null],
        ['budget' => 80, 'party_size' => 2, 'dietary' => [], 'preferences' => null],
        ['budget' => 120, 'party_size' => 4, 'dietary' => [], 'preferences' => null],
    ];

    public function handle(AiMenuService $ai): int
    {
        $this->line('Menu fingerprint: '.$ai->contextFingerprint());

        $failed = 0;

        foreach (AiMenuService::STARTER_QUESTIONS as $starter) {
            $failed += $this->warm(
                $starter['question'],
                fn () => $ai->answerQuestion($starter['question']),
            );
        }

        foreach (self::BRIEFS as $brief) {
            $failed += $this->warm(
                "meal: \${$brief['budget']} for {$brief['party_size']}",
                fn () => $ai->buildMeal($brief),
            );
        }

        if ($failed > 0) {
            $this->warn("{$failed} call(s) failed — rerun to fill the gaps.");

            return self::FAILURE;
        }

        $this->info('AI cache warm.');

        return self::SUCCESS;
    }

    /**
     * @param  callable(): array{cached:bool}  $call
     */
    private function warm(string $label, callable $call): int
    {
        $started = microtime(true);

        try {
            $result = $call();
        } catch (AiUnavailableException) {
            $this->line(sprintf('  %-46s failed', mb_strimwidth($label, 0, 46)));

            return 1;
        }

        $this->line(sprintf(
            '  %-46s %s %ds',
            mb_strimwidth($label, 0, 46),
            $result['cached'] ? 'cached' : 'primed',
            (int) round(microtime(true) - $started),
        ));

        return 0;
    }
}
