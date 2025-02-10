<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Parser_test extends CI_Controller {

	
	public function __construct()
	{
		parent::__construct();
	}
	
	public function index(){

		//load parser library
		$this->load->library('parser');
		//load url_helper
		$this->load->helper('url');

		$data = array(
			//For simple data parsing
			'username' => 'John Doe',
			'age' => 26,
			'currency' => '€',

			//for multi-dimensional array parsing and array item access
			'invoice' => array(
				'items' => array(
					array(
						'product' => 'Product 1',
						'price' => 100,
						'tax' => 10
					),
					array(
						'product' => 'Product 2',
						'price' => 150,
						'tax' => 15
					),
					array(
						'product' => 'Product 3',
						'price' => 200,
						'tax' => 20
					),
				),
				'totals' => array(
					'subtotal' => 450,
					'tax' => 45,
					'total' => 495
				),
				'invoiced_at' => date('Y-m-d H:i:s')
			),

			//for nested array parsing
			'fruits' => array(
				'red' => array('apple', 'strawberry', 'cherry'),
				'yellow' => array('banana', 'lemon', 'pineapple'),
				'green' => array('kiwi', 'lime', 'avocado')
			)
			

		);

		$template = '
			<!DOCTYPE html>
			<html>
			<head>
				<title>Parser Test</title>
			</head>
			<body>
				<h1>Welcome to Parser Test</h1>
				{if {age} > 18}
					<p>You are an adult.</p>
				{else}
					<p>You are a minor.</p>
				{/if}
				<p>Hello, <strong>{username}</strong>! You are <strong>{age}</strong> years old.</p>
				<p>Your invoice:</p>
				<table border="1">
					<tr>
						<th>Product</th>
						<th>Price</th>
						<th>Tax</th>
					</tr>

					{foreach(invoice[items] as key => value)}
						<tr>
							<td>{value[product]}</td>
							<td>{value[price]} {currency}</td>
							<td>{value[tax]} {currency}</td>
						</tr>
					{/foreach}
 
					<tr>
						<td colspan="2">Subtotal</td>
						<td>{invoice[totals][subtotal]} {currency}</td>
					</tr>
					<tr>
						<td colspan="2">Tax</td>
						<td>{invoice[totals][tax]} {currency}</td>
					</tr>
					<tr>
						<td colspan="2">Total</td>
						<td>{invoice[totals][total]} {currency}</td>
					</tr>
				</table>
				<p>Invoiced at: {invoice[invoiced_at]}</p>

				<p> You can download the invoice <a href="{site_url(download)}">with this helper site_url() parsed link</a></p>

				<p>Here are some fruits:</p>
				<ul>
				{foreach(fruits as key => value)}
					<li>{key} fruits:
					<ul>
						{foreach(value as fruit)}
						<li>{fruit}</li>
						{/foreach}
					</ul>
					</li>
				{/foreach}
				</ul>


			</body>
			</html>
		';


		$this->parser->parse_string($template, $data);
	}

}