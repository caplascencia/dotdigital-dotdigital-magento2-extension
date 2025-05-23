<?php

namespace Dotdigitalgroup\Enterprise\Model\Config\Source\PageBuilder;

use Dotdigitalgroup\Email\Helper\Data;
use Dotdigitalgroup\Email\Model\Apiconnector\Client;
use Exception;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Dotdigitalgroup\Email\Logger\Logger;

class Accounts implements OptionSourceInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var array
     */
    private $accountIds = [];

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Accounts constructor.
     *
     * @param Data $data
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     */
    public function __construct(
        Data $data,
        StoreManagerInterface $storeManager,
        Logger $logger
    ) {
        $this->logger = $logger;
        $this->helper = $data;
        $this->storeManager = $storeManager;
    }

    /**
     * Get list of surveys and forms.
     *
     * @return array
     * @throws LocalizedException
     */
    public function toOptionArray()
    {
        $fields = $accountData = $apiUsers = [];

        // Set up account list starting with the default website
        if ($this->helper->isEnabled(0)) {
            $apiUsers[] = $this->helper->getApiUsername(0);
            $accountOwnerEmail = $this->getAccountOwnerEmail(0);
            $accountData[0] = $accountOwnerEmail . ' (Default)';
        }

        foreach ($this->storeManager->getWebsites(false) as $website) {
            if (!$this->helper->isEnabled($website->getId()) ||
                in_array($this->helper->getApiUsername($website->getId()), $apiUsers)
            ) {
                continue;
            }

            $accountOwnerEmail = $this->getAccountOwnerEmail($website->getId());
            if ($accountOwnerEmail) {
                $accountData[$website->getId()] = $accountOwnerEmail . ' (' . $website->getName() . ')';
            }
        }

        if (count($accountData) === 0) {
            $fields[] = ['value' => null, 'label' => __('-- Disabled --')];
            return $fields;
        }

        ksort($accountData);

        foreach ($accountData as $websiteId => $accountString) {
            $fields[] = [
                'value' => $websiteId,
                'label' => $accountString,
            ];
        }

        return $fields;
    }

    /**
     * Get owner of account email
     *
     * @param int $websiteId
     * @return string|bool
     * @throws Exception
     */
    private function getAccountOwnerEmail($websiteId)
    {
        /** @var \stdClass $accountInfo */
        $accountInfo = $this->helper->getWebsiteApiClient($websiteId)
            ->getAccountInfo();

        if (!isset($accountInfo->id) || in_array($accountInfo->id, $this->accountIds)) {
            $this->logger->debug(sprintf("Could not find account owner email for website %s", $websiteId));

            return 'Account owner email not found';
        }

        $this->accountIds[] = $accountInfo->id;

        return $this->getEmailValueFromProperties($accountInfo->properties) ?: 'Account owner';
    }

    /**
     * Get email
     *
     * @param array $properties
     * @return string|bool
     */
    private function getEmailValueFromProperties($properties)
    {
        foreach ($properties as $property) {
            if ($property->name === 'MainEmail') {
                return $property->value;
            }
        }

        return false;
    }
}
