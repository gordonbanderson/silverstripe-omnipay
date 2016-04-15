<?php
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Service\CaptureService;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;

class ServiceFactoryTest extends PaymentTest
{
    private static $dependencies = array(
        'ServiceFactoryTest_TestService'
    );

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidConfigurationException
     */
    public function testDefaultServices()
    {
        $payment = Payment::create()
            ->setGateway("PaymentExpress_PxPay")
            ->setAmount(123)
            ->setCurrency("GBP");

        //$this->setExpectException(InvalidConfigurationException::class);
        $this->assertInstanceOf(
            '\SilverStripe\Omnipay\Service\AuthorizeService',
            $this->factory->getService($payment, ServiceFactory::INTENT_AUTHORIZE),
            'Intent "authorize" should return an instance of "AuthorizeService".'
        );

        $this->assertInstanceOf(
            '\SilverStripe\Omnipay\Service\PurchaseService',
            $this->factory->getService($payment, ServiceFactory::INTENT_PAYMENT),
            'Intent "payment" must return a PurchaseService when gateway doesn\'t use authorize.'
        );

        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_authorize' => true
        ));

        $this->assertInstanceOf(
            '\SilverStripe\Omnipay\Service\AuthorizeService',
            $this->factory->getService($payment, ServiceFactory::INTENT_PAYMENT),
            'Intent "payment" must return a AuthorizeService when gateway is configured to use authorize.'
        );

        // This will throw an exception, because there's no service for the intent "undefined"
        $this->factory->getService($this->payment, 'undefined');
    }

    public function testCustomService()
    {
        Config::inst()->update('ServiceFactory', 'services', array(
            'purchase' => 'ServiceFactoryTest_TestService'
        ));

        $this->assertInstanceOf(
            'ServiceFactoryTest_TestService',
            $this->factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE),
            'The factory should return the configured service instead of the default one.'
        );

        ServiceFactory::add_extension('ServiceFactoryTest_TestExtension');
        ServiceFactory::add_extension('ServiceFactoryTest_TestExtension2');

        // create a new factory instance that uses the new extensions
        $factory = ServiceFactory::create();

        $factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE);

        // the extension will now take care of creating the purchase service
        $this->assertInstanceOf(
            '\SilverStripe\Omnipay\Service\CaptureService',
            $factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE),
            'The factory should return the service generated by the extension.'
        );

        // by having a correctly named method on the extension, 'test' is a valid intent
        $this->assertInstanceOf(
            'ServiceFactoryTest_TestService',
            $factory->getService($this->payment, 'test'),
            'The extension should create a ServiceFactoryTest_TestService instance for the "test" intent.'
        );

        $catched = null;
        try {
            // this should throw an exception since two extensions try to create a service
            $factory->getService($this->payment, ServiceFactory::INTENT_AUTHORIZE);
        } catch (InvalidConfigurationException $ex) {
            $catched = $ex;
        }

        $this->assertInstanceOf(
            '\SilverStripe\Omnipay\Exception\InvalidConfigurationException',
            $catched,
            'When two extensions create service instances, an exception should be raised.'
        );

        ServiceFactory::remove_extension('ServiceFactoryTest_TestExtension');
        ServiceFactory::remove_extension('ServiceFactoryTest_TestExtension2');

    }
}

class ServiceFactoryTest_TestService extends \SilverStripe\Omnipay\Service\PurchaseService implements TestOnly
{

}

class ServiceFactoryTest_TestExtension extends Extension implements TestOnly
{
    // return some different service for testing
    public function createPurchaseService(Payment $payment)
    {
        return CaptureService::create($payment);
    }

    public function createTestService($payment)
    {
        return ServiceFactoryTest_TestService::create($payment);
    }

    // return some different service for testing
    public function createAuthorizeService(Payment $payment)
    {
        return CaptureService::create($payment);
    }
}

class ServiceFactoryTest_TestExtension2 extends Extension implements TestOnly
{
    // return some different service for testing
    public function createAuthorizeService(Payment $payment)
    {
        return CaptureService::create($payment);
    }
}
