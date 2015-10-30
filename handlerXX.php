<?php

/**
 * Яндекс.Деньги
 * Версия 1.2.1
 * Лицензионный договор:
 *	Любое использование Вами программы означает полное и безоговорочное принятие Вами условий лицензионного договора, размещенного по адресу https://money.yandex.ru/doc.xml?id=527132 (далее – «Лицензионный договор»). Если Вы не принимаете условия Лицензионного договора в полном объёме, Вы не имеете права использовать программу в каких-либо целях.
 */
class Shop_Payment_System_HandlerXX extends Shop_Payment_System_Handler
{		
	/* Тестовый или полный режим функциональности. */
	protected $ym_test_mode = 1; // 1 - тестовый, 0 - полный

	/* режим приема средств */ 
	protected $ym_org_mode = 0; // 1 - На расчетный счет организации с заключением договора с Яндекс.Деньгами (юр.лицо), 0 - На счет физического лица в электронной валюте Яндекс.Денег'

	/* Только для физического лица! Идентификатор магазина в системе Яндекс.Деньги. Выдается оператором системы. */
	protected $ym_account = '410011680044609';

	/* Пароль магазина в системе Яндекс.Деньги. Выдается оператором системы. */
	protected $ym_password = 'mEG2ninQcEOc8xTbHy5ApQOf';

	/* Способы оплаты */
	protected $ym_method_pc = 1; /* электронная валюта Яндекс.Деньги. 1 - используется, 0 - нет */
	protected $ym_method_ac = 1; /* банковские карты VISA, MasterCard, Maestro. 1 - используется, 0 - нет */
	protected $ym_method_gp = 1; /* Только для юридического лица! Наличными в кассах и терминалах партнеров. 1 - используется, 0 - нет */
	protected $ym_method_mc = 1; /* Только для юридического лица! Оплата со счета мобильного телефона. 1 - используется, 0 - нет */
	protected $ym_method_wm = 1; /* Только для юридического лица! Электронная валюта WebMoney. 1 - используется, 0 - нет */
	protected $ym_method_ab = 1; /* Только для юридического лица! АльфаКлик. 1 - используется, 0 - нет */
	protected $ym_method_sb = 1; /* Только для юридического лица! Сбербанк Онлайн. 1 - используется, 0 - нет */
	protected $ym_method_ma = 1; /* Только для юридического лица! MasterPass. 1 - используется, 0 - нет */
	protected $ym_method_pb = 1; /* Только для юридического лица! Интернет-банк Промсвязьбанка. 1 - используется, 0 - нет */
	protected $ym_method_qw = 1; /* Только для юридического лица! Оплата через QIWI Wallet. 1 - используется, 0 - нет */
	protected $ym_method_qp = 1; /* Только для юридического лица! Оплата через доверительный платеж (Куппи.ру). 1 - используется, 0 - нет */

	/* Только для юридического лица! Идентификатор вашего магазина в Яндекс.Деньгах (ShopID) */
	protected $ym_shopid = 101;

	/* Только для юридического лица! Идентификатор витрины вашего магазина в Яндекс.Деньгах (scid) */
	protected $ym_scid = 51642;

	// id валюты, в которой будет производиться рассчет суммы
	protected $ym_currency_id = 1; // 1 - рубли (RUR), 2 - евро (EUR), 3 - доллары (USD)

	/* Код валюты, в которой будет производиться оплата в Яндекс-Деньги  */
	protected $ym_orderSumCurrencyPaycash = 643; /* Возможные значения: 643 — рубль Российской Федерации; 10643 — тестовая валюта (демо-рублики демо-системы «Яндекс.Деньги») */

	/* Вызывается на 4-ом шаге оформления заказа*/
	public function execute()
	{
		parent::execute();

		$this->printNotification();

		return $this;
	}

	/* вычисление суммы товаров заказа */
	public function getSumWithCoeff()
	{
		return Shop_Controller::instance()->round(($this->ym_currency_id > 0
				&& $this->_shopOrder->shop_currency_id > 0
			? Shop_Controller::instance()->getCurrencyCoefficientInShopCurrency(
				$this->_shopOrder->Shop_Currency,
				Core_Entity::factory('Shop_Currency', $this->ym_currency_id)
			)
			: 0) * $this->_shopOrder->getAmount() );
	}

	protected function _processOrder()
	{
		parent::_processOrder();

		// Установка XSL-шаблонов в соответствии с настройками в узле структуры
		$this->setXSLs();

		// Отправка писем клиенту и пользователю
		$this->send();

		return $this;
	}

	/* обработка ответа от платёжной системы */
	public function paymentProcessing(){
			$this->ProcessResult();
			return TRUE;
	}

	public function checkSign($callbackParams){
		if ($this->ym_org_mode){
			$string = $callbackParams['action'].';'.$callbackParams['orderSumAmount'].';'.$callbackParams['orderSumCurrencyPaycash'].';'.$callbackParams['orderSumBankPaycash'].';'.$callbackParams['shopId'].';'.$callbackParams['invoiceId'].';'.$callbackParams['customerNumber'].';'.$this->ym_password;
			$md5 = strtoupper(md5($string));
			return (strtoupper($callbackParams['md5'])==$md5);
		}else{
			$string = $callbackParams['notification_type'].'&'.$callbackParams['operation_id'].'&'.$callbackParams['amount'].'&'.$callbackParams['currency'].'&'.$callbackParams['datetime'].'&'.$callbackParams['sender'].'&'.$callbackParams['codepro'].'&'.$this->ym_password.'&'.$callbackParams['label'];
			$check = (sha1($string) == $callbackParams['sha1_hash']);
			if (!$check){
				header('HTTP/1.0 401 Unauthorized');
				return false;
			}
			return true;
		}
	}

	public function sendCode($callbackParams, $code, $message=''){
		if (!$this->ym_org_mode) return;
		header("Content-type: text/xml; charset=utf-8");
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<'.$callbackParams['action'].'Response performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->ym_shopid.'" techmessage="'.$message.'"/>';
		echo $xml;
	}

	/* оплачивает заказ */
	function ProcessResult()
	{
		$callbackParams = $_POST;
		$order_id = false;
		$order_id = (int)$callbackParams[(isset($_POST["label"]))?"label":"orderNumber"];
		if ($this->checkSign($callbackParams)){
			if ($callbackParams['action'] == 'paymentAviso' || !$this->ym_org_mode){
				if ($order_id > 0){
					$oShop_Order = $this->_shopOrder;
					$this->shopOrder($oShop_Order)->shopOrderBeforeAction(clone $oShop_Order);
					$oShop_Order->system_information = "Заказ оплачен через систему Яндекс.Деньги.\n";
					$oShop_Order->paid();
					$this->setXSLs();
					$this->send();
				}
				$this->sendCode($callbackParams, 0, 'Order completed.');
			}else{
				$this->sendCode($callbackParams, 0, 'Order is exist.');
			}
		}else{
			$this->sendCode($callbackParams, 1, 'md5 bad');
		}
		die();
	}

	/* печатает форму отправки запроса на сайт платёжной системы */
	public function getNotification()
	{
		$Sum = $this->getSumWithCoeff();

		$oSiteUser = Core::moduleIsActive('siteuser')
			? Core_Entity::factory('Siteuser')->getCurrent()
			: NULL;
		
		$oSite_Alias = $this->_shopOrder->Shop->Site->getCurrentAlias();
		$site_alias = !is_null($oSite_Alias) ? $oSite_Alias->name : '';
		$shop_path = $this->_shopOrder->Shop->Structure->getPath();
		$handler_url = 'http://'.$site_alias.$shop_path . "cart/?order_id={$this->_shopOrder->id}";

		$successUrl = $handler_url . "&payment=success";
		$failUrl = $handler_url . "&payment=fail";

		?>
		<h2>Оплата через систему Яндекс.Деньги</h2>
		
		<form method="POST" action="<?php echo $this->getFormUrl(); ?>">
			<?php if ($this->ym_org_mode){ ?>
				<input class="wide" name="scid" value="<?php echo $this->ym_scid; ?>" type="hidden">
				<input type="hidden" name="ShopID" value="<?php echo $this->ym_shopid; ?>">
				<input type="hidden" name="CustomerNumber" value="<?php echo (is_null($oSiteUser) ? 0 : $oSiteUser->id); ?>">
				<input type="hidden" name="orderNumber" value="<?php echo $this->_shopOrder->id; ?>">
				<input type="hidden" name="shopSuccessURL" value="<?php echo $successUrl; ?>">
				<input type="hidden" name="shopFailURL" value="<?php echo $failUrl; ?>">
				<input type="hidden" name="cms_name" value="hostcms">
			<?php }else {?>
				   <input type="hidden" name="receiver" value="<?php echo $this->ym_account; ?>">
				   <input type="hidden" name="formcomment" value="<?php echo $site_alias;?>">
				   <input type="hidden" name="short-dest" value="<?php echo $site_alias;?>">
				   <input type="hidden" name="writable-targets" value="false">
				   <input type="hidden" name="comment-needed" value="true">
				   <input type="hidden" name="label" value="<?php echo $this->_shopOrder->id;?>">
				   <input type="hidden" name="quickpay-form" value="shop">
					<input type="hidden" name="successUrl" value="<?php echo $successUrl; ?>"> 

				   <input type="hidden" name="targets" value="Заказ <?php echo $this->_shopOrder->id;?>">
				   <input type="hidden" name="sum" value="<?php echo $Sum;?>" data-type="number" >
				   <input type="hidden" name="comment" value="<?php echo $this->_shopOrder->description;?>" >
				   <input type="hidden" name="need-fio" value="true">
				   <input type="hidden" name="need-email" value="true" >
				   <input type="hidden" name="need-phone" value="false">
				   <input type="hidden" name="need-address" value="false">
	   
			<?php } ?>
				<style>
					.ym_table tr td{
						padding: 10px;
					}
					.ym_table td{
						padding: 10px;
					}
				</style>
				<table class="ym_table" border = "1" cellspacing = "20" width = "80%" bgcolor = "#FFFFFF" align = "center" bordercolor = "#000000">
					<tr>
						<td>Сумма, руб.</td>
						<td> <input type="text" name="Sum" value="<?php echo $Sum?>" readonly="readonly"> </td>
					</tr>
					
					<tr>
						<td>Способ оплаты</td>
						<td> 
								<select name="paymentType">
								<?php if ($this->ym_method_pc){?>
									<option value="PC">Оплата из кошелька в Яндекс.Деньгах</option>
								<?php } ?>
								<?php if ($this->ym_method_ac){?>
									<option value="AC">Оплата с произвольной банковской карты</option>
								<?php } ?>
								<?php if ($this->ym_method_gp && $this->ym_org_mode){?>
									<option value="GP">Оплата наличными через кассы и терминалы</option>
								<?php } ?>
								<?php if ($this->ym_method_mc && $this->ym_org_mode){?>
									<option value="MC">Платеж со счета мобильного телефона</option>
								<?php } ?>
								<?php if ($this->ym_method_ab && $this->ym_org_mode){?>
									<option value="AB">Оплата через Альфа-Клик</option>
								<?php } ?>
								<?php if ($this->ym_method_sb && $this->ym_org_mode){?>
									<option value="SB">Оплата через Сбербанк: оплата по SMS или Сбербанк Онлайн</option>
								<?php } ?>
								<?php if ($this->ym_method_wm && $this->ym_org_mode){?>
									<option value="WM">Оплата из кошелька в системе WebMoney</option>
								<?php } ?>
								<?php if ($this->ym_method_ma && $this->ym_org_mode){?>
									<option value="MA">Оплата через MasterPass</option>
								<?php } ?>
								<?php if ($this->ym_method_pb && $this->ym_org_mode){?>
									<option value="PB">Оплата через интернет-банк Промсвязьбанка</option>
								<?php } ?>
								<?php if ($this->ym_method_qw && $this->ym_org_mode){?>
									<option value="QW">Оплата через QIWI Wallet</option>
								<?php } ?>
								<?php if ($this->ym_method_qp && $this->ym_org_mode){?>
									<option value="QP">Оплата через доверительный платеж (Куппи.ру)</option>
								<?php } ?>
							</select>
						</td>
					</tr>
				</table>

				<table border="0" cellspacing="1" align="center"  width = "80%" bgcolor="#CCCCCC" >
					<tr bgcolor="#FFFFFF">
						<td width="490"></td>
						<td width="48"><input type="submit" name = "BuyButton" value = "Оплатить"></td>
					</tr>
				</table>
		</form>
	<?php
	}

	public function getInvoice(){
		return $this->getNotification();
	}

	public function getFormUrl(){
		$url ='https://';
		$url .= ($this->ym_test_mode)?'demo':'';
		if (!$this->ym_org_mode){
			return $url.'money.yandex.ru/quickpay/confirm.xml';
		}else{
			return $url.'money.yandex.ru/eshop.xml';
		}
	}
}
