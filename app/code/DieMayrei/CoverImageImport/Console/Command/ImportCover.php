<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Console\Command;

use DieMayrei\CoverImageImport\Cron\FetchCover;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCover extends Command
{
    private State $appState;
    private FetchCover $fetchCover;

    public function __construct(
        State $appState,
        FetchCover $fetchCover,
        ?string $name = null
    ) {
        $this->appState = $appState;
        $this->fetchCover = $fetchCover;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('diemayrei:cover:import');
        $this->setDescription('Import cover images for all configured magazines and update product/category images');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Exception $e) {
            // Area code already set
        }

        $output->writeln('<info>Starting cover image import...</info>');

        try {
            $this->fetchCover->execute();
            $output->writeln('<info>Cover image import completed successfully.</info>');
            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }
}
