<?php
namespace SilverStripe\Omnipay\Admin\GridField;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Exception\Exception;

/**
 * A GridField button that can be used to refund a payment
 *
 * @package SilverStripe\Omnipay\Admin\GridField
 */
class GridFieldRefundAction extends GridFieldPaymentAction
{
    /**
     * Which GridField actions are this component handling
     *
     * @param \GridField $gridField
     * @return array
     */
    public function getActions($gridField)
    {
        return array('refundpayment');
    }

    /**
     *
     * @param \GridField $gridField
     * @param \DataObject $record
     * @param string $columnName
     * @return string|null - the HTML for the column
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        if (!($record instanceof \Payment)) {
            return null;
        }

        if (!$record->canRefund()) {
            return null;
        }

        /** @var \GridField_FormAction $field */
        $field = \GridField_FormAction::create(
            $gridField,
            'RefundPayment' . $record->ID,
            false,
            'refundpayment',
            array('RecordID' => $record->ID)
        )
            ->addExtraClass('gridfield-button-refund')
            ->setAttribute('title', _t('GridFieldRefundAction.Title', 'Refund Payment'))
            ->setAttribute('data-icon', 'button-refund')
            ->setDescription(_t('GridFieldRefundAction.Description', 'Refund a captured payment'));

        return $field->Field();
    }

    /**
     * Handle the actions and apply any changes to the GridField
     *
     * @param \GridField $gridField
     * @param string $actionName
     * @param mixed $arguments
     * @param array $data - form data
     * @return void
     * @throws \ValidationException when there was an error
     */
    public function handleAction(\GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'refundpayment') {
            $item = $gridField->getList()->byID($arguments['RecordID']);
            if (!($item instanceof \Payment)) {
                return;
            }

            /** @var ServiceFactory $factory */
            $factory = ServiceFactory::create();
            $refundService = $factory->getService($item, ServiceFactory::INTENT_REFUND);

            try {
                $serviceResponse = $refundService->initiate();
            } catch (Exception $ex){
                throw new \ValidationException(
                    _t('GridFieldRefundAction.RefundError', 'Unable to refund payment. An error occurred.'), 0);
            }

            if ($serviceResponse->isError()) {
                throw new \ValidationException(
                    _t('GridFieldRefundAction.RefundError', 'Unable to refund payment. An error occurred.'), 0);
            }
        }
    }
}
