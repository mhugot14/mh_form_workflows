<?php
/**
 * Reiter „Anmeldung | Termine verwalten“ - nur für berechtigte Lehrkräfte sichtbar.
 *
 * @var bool   $can_manage
 * @var string $tab       anmeldung|termine
 * @var string $page_url
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( empty( $can_manage ) ) return;
$base = remove_query_arg( [ 'mh_ns_tab', 'mh_ns_typ', 'mh_ns_wochen' ], $page_url );
?>
<nav class="mh-ns-tabs">
	<a href="<?= esc_url( $base ) ?>#mh-ns" class="<?= 'termine' !== $tab ? 'is-active' : '' ?>">Anmeldung</a>
	<a href="<?= esc_url( add_query_arg( 'mh_ns_tab', 'termine', $base ) ) ?>#mh-ns" class="<?= 'termine' === $tab ? 'is-active' : '' ?>">Termine verwalten</a>
</nav>
