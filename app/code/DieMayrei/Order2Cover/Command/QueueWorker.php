<?php


namespace DieMayrei\Order2Cover\Command;

use Magento\Framework\App\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueueWorker extends Command
{

    protected function configure()
    {
        $this->setName('diemayrei:order2cover')
            ->setDescription('Transmits queued orders to the Cover ERP system');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cronQueueWorker = ObjectManager::getInstance()->get('DieMayrei\Order2Cover\Cron\QueueWorker');
        $cronQueueWorker->execute();

        return Command::SUCCESS;
    }
}
