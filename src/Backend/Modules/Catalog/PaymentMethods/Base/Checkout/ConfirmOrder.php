<?php

namespace Backend\Modules\Catalog\PaymentMethods\Base\Checkout;

use Backend\Modules\Catalog\Domain\Order\Event\OrderUpdated;
use Backend\Modules\Catalog\Domain\Order\Exception\OrderNotFound;
use Backend\Modules\Catalog\Domain\Order\Order;
use Backend\Modules\Catalog\Domain\Order\OrderRepository;
use Backend\Modules\Catalog\Domain\OrderHistory\Command\CreateOrderHistory;
use Backend\Modules\Catalog\Domain\OrderStatus\Exception\OrderStatusNotFound;
use Backend\Modules\Catalog\Domain\OrderStatus\OrderStatus;
use Backend\Modules\Catalog\Domain\OrderStatus\OrderStatusRepository;
use Backend\Modules\Catalog\Domain\PaymentMethod\CheckoutPaymentMethodDataTransferObject;
use Common\Core\Model;
use Common\Exception\RedirectException;
use Common\ModulesSettings;
use Doctrine\ORM\EntityManager;
use Frontend\Core\Engine\Navigation;
use Frontend\Core\Language\Language;
use Frontend\Core\Language\Locale;
use SimpleBus\Message\Bus\Middleware\MessageBusSupportingMiddleware;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class ConfirmOrder
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $option;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var CheckoutPaymentMethodDataTransferObject
     */
    protected $data;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Locale
     */
    protected $language;

    /**
     * @var ModulesSettings
     */
    protected $settings;

    /**
     * @var MessageBusSupportingMiddleware
     */
    protected $commandBus;

    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * Confirm order constructor.
     *
     * @param string $name
     * @param string $option
     */
    public function __construct(string $name, string $option)
    {
        $this->name                  = $name;
        $this->option                = $option;
        $this->language              = Locale::frontendLanguage();
        $this->settings              = Model::get('fork.settings');
        $this->commandBus            = Model::get('command_bus');
        $this->eventDispatcher       = Model::get('event_dispatcher');
        $this->entityManager         = Model::get('doctrine.orm.entity_manager');
        $this->orderRepository       = Model::get('catalog.repository.order');
        $this->orderStatusRepository = Model::get('catalog.repository.order_status');
    }

    /**
     * Set the order
     *
     * @param Order $order
     *
     * @return void
     */
    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * Set the payment data
     *
     * @param CheckoutPaymentMethodDataTransferObject $data
     *
     * @return void
     */
    public function setData(CheckoutPaymentMethodDataTransferObject $data): void
    {
        $this->data = $data;
    }

    /**
     * Set the request
     *
     * @param Request $request
     *
     * @return void
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Get a setting
     *
     * @param string $key
     * @param mixed $defaultValue
     * @param boolean $includeLanguage
     *
     * @return mixed
     */
    protected function getSetting(string $key, $defaultValue = null, $includeLanguage = true)
    {
        $baseKey = $this->name;

        if ($includeLanguage) {
            $baseKey .= '_' . $this->language->getLocale();
        }

        return $this->settings->get('Catalog', $baseKey . '_' . $key, $defaultValue);
    }

    /**
     * Redirect
     *
     * @param string $url
     * @param int $code
     *
     * @throws RedirectException
     *
     * @return void
     */
    protected function redirect(string $url, int $code = RedirectResponse::HTTP_FOUND): void
    {
        throw new RedirectException('Redirect', new RedirectResponse($url, $code));
    }

    /**
     * Go to the 404 page
     *
     * @throws RedirectException
     *
     * @return void
     */
    protected function goToErrorPage(): void
    {
        $this->redirect(Navigation::getUrl(404));
    }

    /**
     * Go to the store order page
     *
     * @throws RedirectException
     *
     * @return void
     */
    protected function goToPostPaymentPage(): void
    {
        $this->goToPage('post-payment', ['order_id' => $this->order->getId()]);
    }

    /**
     * Go to the success page
     *
     * @throws RedirectException
     *
     * @return void
     */
    protected function goToSuccessPage(): void
    {
        $this->goToPage(Language::lbl('PaymentSuccessUrl'));
    }

    /**
     * Go to the cancelled page
     *
     * @throws RedirectException
     *
     * @return void
     */
    protected function goToCancelledPage(): void
    {
        $this->goToPage(Language::lbl('PaymentCancelledUrl'));
    }

    /**
     * Go to an given cart page
     *
     * @param string $page
     * @param array $parameters
     *
     * @throws RedirectException
     *
     * @return void
     */
    private function goToPage(string $page, array $parameters = []): void
    {
        $url = Navigation::getUrlForBlock('Catalog', 'Cart') . '/' . $page;

        // Add extra parameters
        if ($parameters) {
            $query = [];

            foreach ($parameters as $key => $value) {
                $query[] = $key .'='. $value;
            }

            $url .= '?'. implode('&', $query);
        }

        throw new RedirectException('Redirect', new RedirectResponse($url, RedirectResponse::HTTP_FOUND));
    }

    /**
     * Get the order status based on an id
     *
     * @param mixed $orderStatusId
     *
     * @throws OrderStatusNotFound
     *
     * @return OrderStatus
     */
    protected function getOrderStatus($orderStatusId): ?OrderStatus
    {
        return $this->orderStatusRepository->findOneByIdAndLocale(
            $orderStatusId,
            $this->language
        );
    }

    /**
     * Get the order based on an id
     *
     * @param mixed $orderId
     *
     * @throws OrderNotFound
     *
     * @return Order
     */
    protected function getOrder($orderId): ?Order
    {
        return $this->orderRepository->findOneById(
            $orderId
        );
    }

    /**
     * Post payment action
     *
     * @throws RedirectException
     *
     * @return void
     */
    public abstract function prePayment(): void;

    /**
     * Pre payment action
     *
     * @throws RedirectException
     *
     * @return void
     */
    public abstract function postPayment(): void;

    /**
     * Handle a optional webhook call from the payment provider
     *
     * @return mixed
     */
    public function runWebhook()
    {
        return;
    }

    /**
     * Create a update order status
     *
     * @param Order $order
     * @param integer $statusId
     * @param string $message
     * @param bool $notify
     *
     * @throws OrderStatusNotFound
     *
     * @return void
     */
    protected function updateOrderStatus(Order $order, int $statusId, string $message, bool $notify):void
    {
        $createOrderHistory = new CreateOrderHistory();
        $createOrderHistory->order = $order;
        $createOrderHistory->orderStatus = $this->getOrderStatus($statusId);
        $createOrderHistory->message = $message;
        $createOrderHistory->notify = $notify;
        $this->commandBus->handle($createOrderHistory);

        // Trigger an event to notify or not
        $this->eventDispatcher->dispatch(
            OrderUpdated::EVENT_NAME,
            new OrderUpdated($this->order, $createOrderHistory->getOrderHistoryEntity())
        );
    }
}
