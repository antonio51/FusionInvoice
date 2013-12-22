<?php namespace FI\Storage\Eloquent\Repositories;

use FI\Storage\Eloquent\Models\PaymentCustom;

class PaymentCustomRepository implements \FI\Storage\Interfaces\PaymentCustomRepositoryInterface {

	public function save($input, $paymentId)
	{
		$record = (PaymentCustom::find($paymentId)) ?: new PaymentCustom;

		$record->payment_id = $paymentId;
		
		$record->fill($input);

		$record->save();
	}

}