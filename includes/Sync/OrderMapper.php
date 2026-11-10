<?php
/**
 * Map a WooCommerce order to the DabDash order_upsert row shape.
 *
 * @package DabDashSync
 */

namespace DabDashSync\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the wordpress_commerce_import / order_upsert payload for one WC order.
 */
final class OrderMapper {

	/**
	 * @param \WC_Order $order Woo order.
	 * @return array<string, mixed>
	 */
	public function to_row( $order ) {
		$billing  = array(
			'first_name' => (string) $order->get_billing_first_name(),
			'last_name'  => (string) $order->get_billing_last_name(),
			'company'    => (string) $order->get_billing_company(),
			'address_1'  => (string) $order->get_billing_address_1(),
			'address_2'  => (string) $order->get_billing_address_2(),
			'city'       => (string) $order->get_billing_city(),
			'state'      => (string) $order->get_billing_state(),
			'postcode'   => (string) $order->get_billing_postcode(),
			'country'    => (string) $order->get_billing_country(),
			'email'      => (string) $order->get_billing_email(),
			'phone'      => (string) $order->get_billing_phone(),
		);
		$shipping = array(
			'first_name' => (string) $order->get_shipping_first_name(),
			'last_name'  => (string) $order->get_shipping_last_name(),
			'company'    => (string) $order->get_shipping_company(),
			'address_1'  => (string) $order->get_shipping_address_1(),
			'address_2'  => (string) $order->get_shipping_address_2(),
			'city'       => (string) $order->get_shipping_city(),
			'state'      => (string) $order->get_shipping_state(),
			'postcode'   => (string) $order->get_shipping_postcode(),
			'country'    => (string) $order->get_shipping_country(),
			'phone'      => (string) $order->get_shipping_phone(),
		);

		$line_items = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			$line_items[] = array(
				'product_id'     => (string) $item->get_product_id(),
				'name'           => (string) $item->get_name(),
				'sku'            => $product ? (string) $product->get_sku() : '',
				'quantity'       => (int) $item->get_quantity(),
				'total'          => (string) $item->get_total(),
				'subtotal'       => (string) $item->get_subtotal(),
				'variation_name' => $item->get_variation_id() ? (string) $item->get_name() : null,
			);
		}

		$coupons = array();
		foreach ( $order->get_coupon_codes() as $code ) {
			$coupons[] = array( 'code' => (string) $code );
		}

		$created = $order->get_date_created();

		return array(
			'id'                    => (string) $order->get_id(),
			'status'                => (string) $order->get_status(),
			'currency'              => (string) $order->get_currency(),
			'total'                 => (string) $order->get_total(),
			'subtotal'              => (string) $order->get_subtotal(),
			'shipping_total'        => (string) $order->get_shipping_total(),
			'discount_total'        => (string) $order->get_discount_total(),
			'total_tax'             => (string) $order->get_total_tax(),
			'customer_email'        => (string) $order->get_billing_email(),
			'customer_name'         => trim( $order->get_formatted_billing_full_name() ),
			'customer_phone'        => (string) $order->get_billing_phone(),
			'customer_note'         => (string) $order->get_customer_note(),
			'payment_method'        => (string) $order->get_payment_method(),
			'payment_method_title'  => (string) $order->get_payment_method_title(),
			'date_created'          => $created ? $created->date( 'c' ) : gmdate( 'c' ),
			'billing'               => $billing,
			'shipping'              => $shipping,
			'line_items'            => $line_items,
			'coupon_lines'          => $coupons,
		);
	}
}
