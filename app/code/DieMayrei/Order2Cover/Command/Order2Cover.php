<?php


namespace DieMayrei\Order2Cover\Command;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Order2Cover extends Command
{

    protected function configure()
    {
        $this->setName('diemayrei:order2cover_observer');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ObjectManager::getInstance()->get('\Magento\Framework\App\State')->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
        $cronQueueWorker = ObjectManager::getInstance()->get('DieMayrei\Order2Cover\Observer\Order2Cover');
        $cronQueueWorker->execute(new Observer());
        return 0;
    }
}
