<?php

namespace Backend\Modules\Catalog\Domain\Account;

use Backend\Modules\Catalog\Domain\Account\Command\CreateShipmentAddress;
use Symfony\Component\Validator\Constraints as Assert;

class AccountGuestDataTransferObject extends AddressDataTransferObject
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     *
     * @Assert\NotBlank(message="err.FieldIsRequired")
     * @Assert\Email(
     *     message = "err.EmailIsRequired",
     *     checkMX = true
     * )
     */
    public $email_address;

    public $same_shipping_address = true;

    public function toShipmentAddress(): CreateShipmentAddress
    {
        $shipmentAddress = new CreateShipmentAddress();
        $shipmentAddress->first_name = $this->first_name;
        $shipmentAddress->last_name = $this->last_name;
        $shipmentAddress->phone = $this->phone;
        $shipmentAddress->email_address = $this->email_address;
        $shipmentAddress->street = $this->street;
        $shipmentAddress->house_number = $this->house_number;
        $shipmentAddress->house_number_addition = $this->house_number_addition;
        $shipmentAddress->city = $this->city;
        $shipmentAddress->zip_code = $this->zip_code;

        return $shipmentAddress;
    }
}
