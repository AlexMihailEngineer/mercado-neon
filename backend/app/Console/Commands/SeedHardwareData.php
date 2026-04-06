<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\IngestionService;
use App\Strategies\HardwareApiStrategy;

class SeedHardwareData extends Command
{
    /**
     * The name and signature of the console command.
     * Use the specific naming convention for the MVP.
     */
    protected $signature = 'app:seed-hardware-data';

    /**
     * The console command description.
     */
    protected $description = 'Ingest hardware data from DummyJSON for the Cyberpunk catalog';

    /**
     * Execute the console command.
     */
    public function handle(IngestionService $service): void
    {
        $this->info('🚀 Initializing Cyberpunk Hardware Ingestion...');

        // Instantiate the strategy locally as it's a specific implementation choice for this command
        $strategy = new HardwareApiStrategy();

        $this->output->progressStart();

        try {
            $service->run($strategy);
            $this->output->progressFinish();
            $this->info('✅ Ingestion complete. Data synced to Typesense via Scout.');
        } catch (\Exception $e) {
            $this->error('❌ Critical Failure: ' . $e->getMessage());
        }
    }
}
