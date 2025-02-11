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

		// $data = array(
		// 	//For simple data parsing
		// 	'username' => 'John Doe',
		// 	'age' => 26,
		// 	'currency' => '€',

		// 	//for multi-dimensional array parsing and array item access
		// 	'invoice' => array(
		// 		'items' => array(
		// 			array(
		// 				'product' => 'Product 1',
		// 				'price' => 100,
		// 				'tax' => 10
		// 			),
		// 			array(
		// 				'product' => 'Product 2',
		// 				'price' => 150,
		// 				'tax' => 15
		// 			),
		// 			array(
		// 				'product' => 'Product 3',
		// 				'price' => 200,
		// 				'tax' => 20
		// 			),
		// 		),
		// 		'totals' => array(
		// 			'subtotal' => 450,
		// 			'tax' => 45,
		// 			'total' => 495
		// 		),
		// 		'invoiced_at' => date('Y-m-d H:i:s')
		// 	),
		// );

		$data = array(
			'fruits' => array(
				'apple', 
				'strawberry', 
				'cherry'
			),
			'fruits_by_color' => array(
				'red' => array('apple', 'strawberry', 'cherry'),
				'yellow' => array('banana', 'lemon', 'pineapple'),
				'green' => array('kiwi', 'lime', 'avocado')
			),
			'invoice' => array(
				'items' => array(
					0 => array(
						'product' => 'Product 1',
						'price' => 100,
						'tax' => 10
					),
					1 => array(
						'product' => 'Product 2',
						'price' => 150,
						'tax' => 15
					),
					2 => array(
						'product' => 'Product 3',
						'price' => 200,
						'tax' => 20
					),
				)
			)
		);
		

		$template = '
			<p>Esto funciona bien!</p>
			<table>
				<tr>
					<th>Product</th>
					<th>Price</th>
					<th>Tax</th>
				</tr>
				{foreach(invoice[items] as index => product)}
				<tr>
					<td>{product[product]}</td>
					<td>{product[price]}€</td>
					<td>{product[tax]}€</td>
				</tr>
				{/foreach}
			</table>

			

			<p>Esto funciona bien!</p>
			{foreach(fruits as value)}
				<li>{value}</li>
			{/foreach}

			<p>Esto funciona bien!</p>
			{foreach(fruits as key => value)}
				<li>{key} - {value}</li>
			{/foreach}

			<p>Esto debe funcionar con nombres dinámicos, pero no lo hace:</p>
			<ul>
				{foreach(fruits_by_color as color => fruitList)}
					<li>{color} fruits:
					<ul>
						{foreach(fruitList as fruit)}
							<li>{fruit}</li>
						{/foreach}
					</ul>
					</li>
				{/foreach}
			</ul>

			<p>Esto renderiza los elementos que hay en product pero no el index!</p>
			<table>
				<tr>
					<th>Product</th>
					<th>Price</th>
					<th>Tax</th>
				</tr>
				{foreach(invoice[items] as index => product)}
					<tr>
						<td>{index} - {product[product]}</td>
						<td>{index} - {product[price]}€</td>
						<td>{index} - {{product[tax]}€</td>
					</tr>
				{/foreach}
			</table>
		';

		$template = '
			<p>Esto debe funcionar con nombres dinámicos, pero no lo hace:</p>

				{foreach(fruits_by_color as color => fruitList)}
					<p>{color} fruits:</p>
					<ul>
						{foreach(fruitList as key => fruit)}
							<li>{fruit}</li>
						{/foreach}
					</ul>
					</p>
				{/foreach}

			<p>Esto renderiza los elementos que hay en product pero no el index!</p>
			<table>
				<tr>
					<th>Product</th>
					<th>Price</th>
					<th>Tax</th>
				</tr>
				{foreach(invoice[items] as index => product)}
					<tr>
						<td>{index} - {product[product]}</td>
						<td>{index} - {product[price]}€</td>
						<td>{index} - {product[tax]}€</td>
					</tr>
				{/foreach}
			</table>
		';

		$this->parser->parse_string($template, $data);
	}

}