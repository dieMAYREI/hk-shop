<?php


namespace DieMayrei\EmailNotice\Console\Command;

use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendOrderSuccesMail extends Command
{

    /** @var \Magento\Framework\App\State **/
    private $state;

    /** @var string  */
    const ORDERID = 'orderid';


    public function __construct(
        \Magento\Framework\App\State $state
    ) {
        $this->state = $state;
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {

        $options = [
            new InputOption(
                self::ORDERID,
                null,
                InputOption::VALUE_REQUIRED,
                'OrderId'
            )
        ];
        $this->setName('diemayrei:send:ordersuccessmail');
        $this->setDescription('Send Order Success Mail for Order ID:');
        $this->setDefinition($options);

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return $this|int|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);

        /**
         * Muss leider über den ObjectManager gemacht werden da sonst Fehler: Area code is not set
         */
        $objectManager = ObjectManager::getInstance();
        $orderSuccess = $objectManager->create('DieMayrei\EmailNotice\Cron\OrderSuccessMail');

        $order_id = $input->getOption(self::ORDERID);

        $mutex = new \DieMayrei\Order2Cover\Helper\MyMutex(__DIR__ . '/../../Cron/OrderSuccessMail.php');

        return $mutex->synchronized(function () use ($orderSuccess, $output, $order_id) {
            if (!$order_id) {
                $order_id = $orderSuccess->getOrders();
                $output->writeln("Es können auch einzelne OrderIds ausgeführt werden: --orderid=3243");
            } else {
                if (substr_count($order_id, ',')) {
                    $order_id = explode(',', $order_id);
                }
            }
            if (is_array($order_id)) {
                foreach ($order_id as $id) {
                    if (isset($id['entity_id'])) {
                        $orderSuccess->simulateexecute($id['entity_id']);
                        $output->writeln('<info>Bestellung #' . $id['entity_id'] . ' gesendet</info>');
                    } else {
                        $orderSuccess->simulateexecute($id);
                        $output->writeln('<info>Bestellung #' . $id . ' gesendet</info>');
                    }
                }
            } else {
                $orderSuccess->simulateexecute($order_id);
                $output->writeln('<info>Bestellung #' . $order_id . ' gesendet</info>');
            }

            return 1;
        });

        return 0;
    }
}
