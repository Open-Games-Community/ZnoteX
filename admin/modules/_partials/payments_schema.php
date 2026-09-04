<?php

return array(

		'PayPal' => array(
			'icon'   => 'fa-paypal',
			'help'   => 'Payments land through ipn.php. The address below must be the one that receives them.',
			'fields' => array(
				'paypal.enabled'             => array('label' => 'PayPal enabled', 'type' => 'bool'),
				'paypal.email'               => array('label' => 'PayPal account e-mail', 'type' => 'text'),
				'paypal.currency'            => array('label' => 'Currency', 'type' => 'text', 'help' => 'Three-letter code, e.g. EUR, USD, BRL.'),
				'paypal.points_per_currency' => array('label' => 'Points per unit of currency', 'type' => 'int', 'help' => 'Only used to work out the bonus percentages shown to players.'),
				'paypal.showBonus'           => array('label' => 'Show bonus percentages', 'type' => 'bool'),
			),
		),

		'PagSeguro' => array(
			'icon'   => 'fa-credit-card',
			'help'   => 'Brazilian gateway. Sandbox mode switches every URL to the test environment.',
			'fields' => array(
				'pagseguro.enabled'      => array('label' => 'PagSeguro enabled', 'type' => 'bool'),
				'pagseguro.sandbox'      => array('label' => 'Sandbox mode', 'type' => 'bool'),
				'pagseguro.email'        => array('label' => 'Account e-mail', 'type' => 'text'),
				'pagseguro.token'        => array('label' => 'API token', 'type' => 'secret'),
				'pagseguro.currency'     => array('label' => 'Currency', 'type' => 'text'),
				'pagseguro.product_name' => array('label' => 'Product name', 'type' => 'text'),
				'pagseguro.price'        => array('label' => 'Price', 'type' => 'int'),
			),
		),

		'PayGol SMS' => array(
			'icon'   => 'fa-mobile',
			'help'   => 'PayGol keeps a large share of each payment and pays out to PayPal once the balance passes their threshold.',
			'fields' => array(
				'paygol.enabled'   => array('label' => 'PayGol enabled', 'type' => 'bool'),
				'paygol.serviceID' => array('label' => 'Service ID', 'type' => 'int'),
				'paygol.secretKey' => array('label' => 'Secret key', 'type' => 'secret', 'help' => 'Never share this. It signs the payment callbacks.'),
				'paygol.currency'  => array('label' => 'Currency', 'type' => 'text'),
				'paygol.price'     => array('label' => 'Price', 'type' => 'int'),
				'paygol.points'    => array('label' => 'Points given', 'type' => 'int'),
				'paygol.name'      => array('label' => 'Package name', 'type' => 'text'),
			),
		),

		'OTServers.eu voting' => array(
			'icon'   => 'fa-star',
			'help'   => 'Rewards players with points for voting. Get the token from OTServers.eu under "Encourage players to vote".',
			'fields' => array(
				'otservers_eu_voting.enabled'       => array('label' => 'Voting rewards enabled', 'type' => 'bool'),
				'otservers_eu_voting.secretToken'   => array('label' => 'Secret token', 'type' => 'secret'),
				'otservers_eu_voting.points'        => array('label' => 'Points per vote', 'type' => 'int'),
				'otservers_eu_voting.simpleVoteUrl' => array('label' => 'Vote URL for guests', 'type' => 'text'),
			),
		),
	);
