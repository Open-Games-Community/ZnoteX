<?php

return array(

		t_default('acp.paysec.PayPal', 'PayPal') => array(
			'icon'   => 'fa-paypal',
			'help'   => t_default('acp.paysec.PayPal.help', 'Payments land through ipn.php. The address below must be the one that receives them.'),
			'fields' => array(
				'paypal.enabled'             => array('label' => t_default('acp.pay.paypal.enabled.label', 'PayPal enabled'), 'type' => 'bool'),
				'paypal.email'               => array('label' => t_default('acp.pay.paypal.email.label', 'PayPal account e-mail'), 'type' => 'text'),
				'paypal.currency'            => array('label' => t_default('acp.pay.paypal.currency.label', 'Currency'), 'type' => 'text', 'help' => t_default('acp.pay.paypal.currency.help', 'Three-letter code, e.g. EUR, USD, BRL.')),
				'paypal.points_per_currency' => array('label' => t_default('acp.pay.paypal.points_per_currency.label', 'Points per unit of currency'), 'type' => 'int', 'help' => t_default('acp.pay.paypal.points_per_currency.help', 'Only used to work out the bonus percentages shown to players.')),
				'paypal.showBonus'           => array('label' => t_default('acp.pay.paypal.showBonus.label', 'Show bonus percentages'), 'type' => 'bool'),
			),
		),

		t_default('acp.paysec.Stripe', 'Stripe') => array(
			'icon'   => 'fa-credit-card',
			'help'   => t_default('acp.paysec.Stripe.help', 'Hosted Checkout. Points are credited only by signed webhook after Stripe confirms the session is paid.'),
			'fields' => array(
				'stripe.enabled'             => array('label' => t_default('acp.pay.stripe.enabled.label', 'Stripe enabled'), 'type' => 'bool'),
				'stripe.test_mode'           => array('label' => t_default('acp.pay.stripe.test_mode.label', 'Test mode'), 'type' => 'bool'),
				'stripe.publishable_key'     => array('label' => t_default('acp.pay.stripe.publishable_key.label', 'Publishable key'), 'type' => 'text'),
				'stripe.secret_key'          => array('label' => t_default('acp.pay.stripe.secret_key.label', 'Secret key'), 'type' => 'secret'),
				'stripe.webhook_secret'      => array('label' => t_default('acp.pay.stripe.webhook_secret.label', 'Webhook secret'), 'type' => 'secret', 'help' => t_default('acp.pay.stripe.webhook_secret.help', 'Required. Stripe signs each webhook with this endpoint secret.')),
				'stripe.currency'            => array('label' => t_default('acp.pay.stripe.currency.label', 'Currency'), 'type' => 'text', 'help' => t_default('acp.pay.stripe.currency.help', 'Three-letter code, e.g. EUR, USD, BRL.')),
				'stripe.points_per_currency' => array('label' => t_default('acp.pay.stripe.points_per_currency.label', 'Points per unit of currency'), 'type' => 'int'),
				'stripe.amount_multiplier'   => array('label' => t_default('acp.pay.stripe.amount_multiplier.label', 'Amount multiplier'), 'type' => 'int', 'help' => t_default('acp.pay.stripe.amount_multiplier.help', '100 for EUR/USD/BRL, 1 for zero-decimal currencies.')),
				'stripe.webhook_url'         => array('label' => t_default('acp.pay.stripe.webhook_url.label', 'Webhook URL'), 'type' => 'text'),
				'stripe.showBonus'           => array('label' => t_default('acp.pay.stripe.showBonus.label', 'Show bonus percentages'), 'type' => 'bool'),
			),
		),

		t_default('acp.paysec.Mercado Pago', 'Mercado Pago') => array(
			'icon'   => 'fa-credit-card',
			'help'   => t_default('acp.paysec.Mercado Pago.help', 'Checkout Pro. Points are credited only by signed webhook after Mercado Pago confirms the payment is approved.'),
			'fields' => array(
				'mercadopago.enabled'             => array('label' => t_default('acp.pay.mercadopago.enabled.label', 'Mercado Pago enabled'), 'type' => 'bool'),
				'mercadopago.test_mode'           => array('label' => t_default('acp.pay.mercadopago.test_mode.label', 'Test mode'), 'type' => 'bool'),
				'mercadopago.public_key'          => array('label' => t_default('acp.pay.mercadopago.public_key.label', 'Public key'), 'type' => 'text'),
				'mercadopago.access_token'        => array('label' => t_default('acp.pay.mercadopago.access_token.label', 'Access token'), 'type' => 'secret'),
				'mercadopago.webhook_secret'      => array('label' => t_default('acp.pay.mercadopago.webhook_secret.label', 'Webhook secret'), 'type' => 'secret', 'help' => t_default('acp.pay.mercadopago.webhook_secret.help', 'Required. Mercado Pago signs notifications with this secret.')),
				'mercadopago.currency'            => array('label' => t_default('acp.pay.mercadopago.currency.label', 'Currency'), 'type' => 'text', 'help' => t_default('acp.pay.mercadopago.currency.help', 'Currency supported by your Mercado Pago account, e.g. BRL, MXN, ARS, CLP, COP, PEN, UYU.')),
				'mercadopago.points_per_currency' => array('label' => t_default('acp.pay.mercadopago.points_per_currency.label', 'Points per unit of currency'), 'type' => 'int'),
				'mercadopago.webhook_url'         => array('label' => t_default('acp.pay.mercadopago.webhook_url.label', 'Webhook URL'), 'type' => 'text'),
				'mercadopago.showBonus'           => array('label' => t_default('acp.pay.mercadopago.showBonus.label', 'Show bonus percentages'), 'type' => 'bool'),
			),
		),

		t_default('acp.paysec.PagSeguro', 'PagSeguro') => array(
			'icon'   => 'fa-credit-card',
			'help'   => t_default('acp.paysec.PagSeguro.help', 'Brazilian gateway. Sandbox mode switches every URL to the test environment.'),
			'fields' => array(
				'pagseguro.enabled'      => array('label' => t_default('acp.pay.pagseguro.enabled.label', 'PagSeguro enabled'), 'type' => 'bool'),
				'pagseguro.sandbox'      => array('label' => t_default('acp.pay.pagseguro.sandbox.label', 'Sandbox mode'), 'type' => 'bool'),
				'pagseguro.email'        => array('label' => t_default('acp.pay.pagseguro.email.label', 'Account e-mail'), 'type' => 'text'),
				'pagseguro.token'        => array('label' => t_default('acp.pay.pagseguro.token.label', 'API token'), 'type' => 'secret'),
				'pagseguro.currency'     => array('label' => t_default('acp.pay.pagseguro.currency.label', 'Currency'), 'type' => 'text'),
				'pagseguro.product_name' => array('label' => t_default('acp.pay.pagseguro.product_name.label', 'Product name'), 'type' => 'text'),
				'pagseguro.price'        => array('label' => t_default('acp.pay.pagseguro.price.label', 'Price'), 'type' => 'int'),
			),
		),

		t_default('acp.paysec.PayGol SMS', 'PayGol SMS') => array(
			'icon'   => 'fa-mobile',
			'help'   => t_default('acp.paysec.PayGol SMS.help', 'PayGol keeps a large share of each payment and pays out to PayPal once the balance passes their threshold.'),
			'fields' => array(
				'paygol.enabled'   => array('label' => t_default('acp.pay.paygol.enabled.label', 'PayGol enabled'), 'type' => 'bool'),
				'paygol.serviceID' => array('label' => t_default('acp.pay.paygol.serviceID.label', 'Service ID'), 'type' => 'int'),
				'paygol.secretKey' => array('label' => t_default('acp.pay.paygol.secretKey.label', 'Secret key'), 'type' => 'secret', 'help' => t_default('acp.pay.paygol.secretKey.help', 'Never share this. It signs the payment callbacks.')),
				'paygol.currency'  => array('label' => t_default('acp.pay.paygol.currency.label', 'Currency'), 'type' => 'text'),
				'paygol.price'     => array('label' => t_default('acp.pay.paygol.price.label', 'Price'), 'type' => 'int'),
				'paygol.points'    => array('label' => t_default('acp.pay.paygol.points.label', 'Points given'), 'type' => 'int'),
				'paygol.name'      => array('label' => t_default('acp.pay.paygol.name.label', 'Package name'), 'type' => 'text'),
			),
		),

		t_default('acp.paysec.OTServers.eu voting', 'OTServers.eu voting') => array(
			'icon'   => 'fa-star',
			'help'   => t_default('acp.paysec.OTServers.eu voting.help', 'Rewards players with points for voting. Get the token from OTServers.eu under "Encourage players to vote".'),
			'fields' => array(
				'otservers_eu_voting.enabled'       => array('label' => t_default('acp.pay.otservers_eu_voting.enabled.label', 'Voting rewards enabled'), 'type' => 'bool'),
				'otservers_eu_voting.secretToken'   => array('label' => t_default('acp.pay.otservers_eu_voting.secretToken.label', 'Secret token'), 'type' => 'secret'),
				'otservers_eu_voting.points'        => array('label' => t_default('acp.pay.otservers_eu_voting.points.label', 'Points per vote'), 'type' => 'int'),
				'otservers_eu_voting.simpleVoteUrl' => array('label' => t_default('acp.pay.otservers_eu_voting.simpleVoteUrl.label', 'Vote URL for guests'), 'type' => 'text'),
			),
		),
	);
