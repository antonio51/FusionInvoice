<?php

use FI\Classes\Date;
use FI\Classes\NumberFormatter;
use FI\Classes\CustomFields;
use FI\Storage\Interfaces\CustomFieldRepositoryInterface;
use FI\Storage\Interfaces\PaymentCustomRepositoryInterface;
use FI\Storage\Interfaces\PaymentMethodRepositoryInterface;
use FI\Storage\Interfaces\PaymentRepositoryInterface;
use FI\Validators\PaymentValidator;

class PaymentController extends BaseController {

	protected $customField;
	protected $payment;
	protected $paymentCustom;
	protected $paymentMethod;
	protected $validator;
	
	public function __construct(
		CustomFieldRepositoryInterface $customField,
		PaymentCustomRepositoryInterface $paymentCustom,
		PaymentMethodRepositoryInterface $paymentMethod,
		PaymentRepositoryInterface $payment, 
		PaymentValidator $validator)
	{
		$this->customField   = $customField;
		$this->payment       = $payment;
		$this->paymentCustom = $paymentCustom;
		$this->paymentMethod = $paymentMethod;
		$this->validator     = $validator;
	}

	/**
	 * Display paginated list
	 * @return View
	 */
	public function index()
	{
		$payments = $this->payment->getPaged(Input::get('page'));

		return View::make('payments.index')
		->with('payments', $payments);
	}

	/**
	 * Display form for existing record
	 * @param  int $paymentId
	 * @param  int $invoiceId
	 * @return View
	 */
	public function edit($paymentId, $invoiceId)
	{
		$invoice = App::make('FI\Storage\Interfaces\InvoiceRepositoryInterface');

		$payment = $this->payment->find($paymentId);
		$invoice = $invoice->find($invoiceId);
		
		return View::make('payments.form')
		->with('editMode', true)
		->with('payment', $payment)
		->with('paymentMethods', $this->paymentMethod->lists())
		->with('invoice', $invoice)
		->with('customFields', $this->customField->getByTable('payments'));;
	}

	/**
	 * Validate and handle existing record form submission
	 * @param  int $paymentId
	 * @param  int $invoiceId
	 * @return RedirectResponse
	 */
	public function update($paymentId, $invoiceId)
	{
		$input = Input::all();

		$custom = $input['custom'];
		unset($input['custom']);

		if (!$this->validator->validate($input))
		{
			return Redirect::route('payments.edit', array($paymentId, $invoiceId))
			->with('editMode', true)
			->withErrors($this->validator->errors())
			->withInput();
		}

        $input['paid_at'] = Date::unformat($input['paid_at']);
        $input['amount']  = NumberFormatter::unformat($input['amount']);

		$this->payment->update($input, $paymentId);
		$this->paymentCustom->save($custom, $paymentId);

		\Event::fire('invoice.modified', array($invoiceId));

		return Redirect::route('payments.index')
		->with('alertInfo', trans('fi.record_successfully_updated'));
	}

	/**
	 * Delete a record
	 * @param  int $paymentId
	 * @param  int $invoiceId
	 * @return RedirectResponse
	 */
	public function delete($paymentId, $invoiceId)
	{
		$this->payment->delete($paymentId);

		\Event::fire('invoice.modified', array($invoiceId));

		return Redirect::route('payments.index')
		->with('alert', trans('fi.record_successfully_deleted'));
	}

	/**
	 * Display the enter payment modal form
	 * @return View
	 */
	public function modalEnterPayment()
	{
		$date = FI\Classes\Date::format();
		
		return View::make('payments._modal_enter_payment')
		->with('invoice_id', Input::get('invoice_id'))
		->with('balance', Input::get('balance'))
		->with('date', $date)
		->with('paymentMethods', $this->paymentMethod->all())
		->with('redirectTo', Input::get('redirectTo'));
	}

	public function ajaxStore()
	{
		$input = Input::all();

		if (!$this->validator->validate($input))
		{
			return json_encode(array('success' => 0, 'message' => $this->validator->errors()->first()));
		}
        
        $input['paid_at'] = Date::unformat($input['paid_at']);
        $input['amount']  = NumberFormatter::unformat($input['amount']);

		$this->payment->create($input);

		\Event::fire('invoice.modified', array($input['invoice_id']));

		return json_encode(array('success' => 1));

	}

}