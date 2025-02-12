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
			//With this, we`ll parse single variables
			'username' => 'John Doe',
			'age' => 26,
			'currency' => '€',

			//With this, we'll parse as a list in a ul
			'fruits' => array(
				'apple', 
				'cherry',
				'banana',
				'melon',
			),

			//With this, we'll parse as a list with keys in a table
			'person' => array(
				'name' => 'John Doe',
				'age' => 26,
				'zipcode' => '12345'
			),

			//For multi-dimensional "keyed" array parsing
			'fruits_by_color' => array(
				'red' => array(
					'apple' => [
						'price' => 1,
						'weight' => 2
					],
					'strawberry' => [
						'price' => 2,
						'weight' => 3
					],
					'cherry' => [
						'price' => 3,
						'weight' => 4
					]
				),

				'yellow' => array(
					'banana' => [
						'price' => 1,
						'weight' => 2
					],
					'lemon' => [
						'price' => 2,
						'weight' => 3
					],
					'pineapple' => [
						'price' => 3,
						'weight' => 4
					]
					),

				'green' => array(
					'kiwi' => [
						'price' => 1,
						'weight' => 2
					],
					'pear' => [
						'price' => 2,
						'weight' => 3
					],
					'apple' => [
						'price' => 3,
						'weight' => 4
					]
				)
			),

			//For multi-dimensional "keyed" and "indexed" array parsing
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

			'number_list' => array(
				array(1,2,3,4),
				array(5,6,7,8),
				array(9,10,11,12)
			),

			'cars' => array(
				array(
					'brand' => 'Ford',
					'model' => 'Focus',
					'year' => 2018
				),
				array(
					'brand' => 'Chevrolet',
					'model' => 'Camaro',
					'year' => 2019
				),
				array(
					'brand' => 'Dodge',
					'model' => 'Challenger',
					'year' => 2020
				)
			),
		);

		$template = '
			<p>Using Switch</p>
				{foreach(fruits as fruit)}
					{switch {fruit}}
						{case cherry}
							<p>{fruit} is red</p>
						{break}
						{case banana}
							<p>{fruit} is yellow</p>
						{break}
						{case melon}
							<p>{fruit} is green</p>
						{break}
					{/switch}
				{/foreach}
			<hr>

			<p>Using If</p>
			{foreach(fruits as fruit)}
				{if {fruit} == cherry}
					<p>{fruit} is red</p>
				{else}
					<p>{fruit} is not red</p>
				{/if}
			{/foreach}
			<hr>

			<p>Render person object iterating key/value with <code>foreach(person as key => value)</code></p>
			<table border="1">
				<tr>
					<th>Key</th>
					<th>Value</th>
				</tr>
				{foreach(person as key => value)}
					<tr>
						<td><strong>{key}:</strong></td>
						<td>{value}</td>
					</tr>
				{/foreach}
			</table>

			<p>Render person by accesing the value directlyby its path with <code>person[key]</code> to extract its value</p>
			<table border="1">
				<tr>
					<th>Key</th>
					<th>Value</th>
				</tr>
				<tr>
					<td><strong>name:</strong></td>
					<td>{person[name]}</td>
				</tr>
				<tr>
					<td><strong>age:</strong></td>
					<td>{person[age]}</td>
				</tr>
				<tr>
					<td><strong>zipcode:</strong></td>
					<td>{person[zipcode]}</td>
				</tr>
			</table>

			<hr>

			<p>Render a simple list with <code>foreach(elements as element)</code></p>
			{foreach(fruits as value)}
				<li>{value}</li>
			{/foreach}

			<hr>

			<p>Render a simple list with <code>foreach(elements as key => element)</code> to display index - value</p>
			{foreach(fruits as key => value)}
				<li>{key} - {value}</li>
			{/foreach}

			<hr>

			<p>Render a simple list made o simple lists with <code>foreach(elements as element)</code></p>
			{foreach(number_list as number)}
				{foreach(number as num)}
					{num}
				{/foreach}
				<br>
			{/foreach}

			<hr>

			<p>Render a multi-dimensional array combining all possible foreachs and accessing the values directly by its path</p>
			<ul>
				{foreach(fruits_by_color as color => fruitList)}
					<li>{color} fruits:
					<ul>
						{foreach(fruitList as fruit => properties)}
							<li>{fruit}\'s weight is {properties[weight]} and you have to pay {properties[price]}€</li>
						{/foreach}
					</ul>
					</li>
				{/foreach}
			</ul>

			<hr>

			<p>Render an array of elements with properties with the ci 3 core syntax <code></code></p>
			<table>
				<tr>
					<th>Brand</th>
					<th>Model</th>
					<th>Year</th>
				</tr>
				{cars}
					<tr>
						<td>{brand}</td>
						<td>{model}</td>
						<td>{year}</td>
					</tr>
				{/cars}
			</table>

			<p>Render a multi-dimensional array combining foreach and accessing the values directly by its path</p>
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

			<hr>

			<p>Render a multi-dimensional array combining foreach and accessing the values directly by its path and its index in array (#)</p>
			<table>
				<tr>
					<th>#</th>
					<th>Product</th>
					<th>Price</th>
					<th>Tax</th>
				</tr>
				{foreach(invoice[items] as index => product)}
					<tr>
						<td>{index}</td>
						<td>{product[product]}</td>
						<td>{product[price]}€</td>
						<td>{product[tax]}€</td>
					</tr>
				{/foreach}
			</table>

			<hr>

			Render a multi-dimensional array combining foreach and accessing the values directly and using the switch statement to render color</p>
			<ul>
				{foreach(fruits_by_color as color => fruitList)}
					{switch {color}}
						{case red}
							<li style="color:{color}">{color} fruits are the best:
						{break}
						{case yellow}
							<li style="color:blue">{color} fruits are {color} but we write it in blue:
						{break}
						{case green}
							<li style="color:{color}">{color} fruits are healthy:
						{break}
					{/switch}

					<ul>
						{foreach(fruitList as fruit => properties)}
							<li>{fruit}\'s weight is {properties[weight]} and you have to pay {properties[price]}€</li>
						{/foreach}
					</ul>
					</li>
				{/foreach}
			</ul>
		';

		$this->parser->parse_string($template, $data);
	}

}