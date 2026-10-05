<?php
/**
 * Expo push message texts and payloads.
 *
 * Kept free of WordPress dependencies so it can be unit tested with plain PHP.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Push_Messages {

	/** Expo accepts at most 100 messages per request. */
	public const BATCH_SIZE = 100;

	/**
	 * Title and body for a status change, or null when the status needs no notification.
	 *
	 * @return array{title: string, body: string}|null
	 */
	public static function for_status( string $status, string $fulfillment, string $order_number ): ?array {
		$texts = array(
			'confirmed'        => array( 'Comanda a fost acceptată', 'Restaurantul a primit comanda #%s și se apucă de ea.' ),
			'preparing'        => array( 'Comanda ta e pe jar', 'Comanda #%s se pregătește acum.' ),
			'on_the_way'       => array( 'Comanda e în drum spre tine', 'Curierul a plecat cu comanda #%s.' ),
			'ready_for_pickup' => array( 'Comanda te așteaptă', 'Comanda #%s e gata de ridicare.' ),
			'completed'        => array( 'pickup' === $fulfillment ? 'Poftă bună!' : 'Comanda a fost livrată', 'Mulțumim pentru comanda #%s!' ),
			'cancelled'        => array( 'Comanda a fost anulată', 'Comanda #%s a fost anulată. Pentru detalii, sună la restaurant.' ),
		);

		if ( ! isset( $texts[ $status ] ) ) {
			return null;
		}

		return array(
			'title' => $texts[ $status ][0],
			'body'  => sprintf( $texts[ $status ][1], $order_number ),
		);
	}

	/**
	 * One message per device token, grouped into Expo-sized batches.
	 *
	 * @param string[]             $tokens
	 * @param array<string, mixed> $data
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	public static function batches( array $tokens, string $title, string $body, array $data ): array {
		$messages = array();
		foreach ( array_values( array_unique( $tokens ) ) as $token ) {
			$messages[] = array(
				'to'        => $token,
				'title'     => $title,
				'body'      => $body,
				'data'      => $data,
				'sound'     => 'default',
				'priority'  => 'high',
				'channelId' => 'comenzi',
			);
		}
		return array_chunk( $messages, self::BATCH_SIZE );
	}

	/**
	 * Tokens Expo reports as no longer registered, matched to the batch by position.
	 *
	 * @param array<int, array<string, mixed>> $batch
	 * @param array<string, mixed>|null        $response Decoded Expo response.
	 * @return string[]
	 */
	public static function dead_tokens( array $batch, ?array $response ): array {
		$tickets = isset( $response['data'] ) && is_array( $response['data'] ) ? array_values( $response['data'] ) : array();
		$dead    = array();
		foreach ( $tickets as $index => $ticket ) {
			if ( ! is_array( $ticket ) || ( $ticket['status'] ?? '' ) !== 'error' ) {
				continue;
			}
			if ( ( $ticket['details']['error'] ?? '' ) === 'DeviceNotRegistered' && isset( $batch[ $index ]['to'] ) ) {
				$dead[] = (string) $batch[ $index ]['to'];
			}
		}
		return $dead;
	}
}
