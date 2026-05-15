<?php
defined( 'ABSPATH' ) || exit;

$manager     = WPM_Language_Manager::instance();
$url_handler = WPM_Url_Handler::instance();

$languages  = $manager->get_all();
$current    = $manager->get_current();
$style      = isset( $attributes['style'] )     ? $attributes['style']     : 'flags';
$show_label = isset( $attributes['showLabel'] ) ? $attributes['showLabel'] : true;
$align_class = ! empty( $attributes['align'] ) ? 'align' . $attributes['align'] : '';
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wpm-language-switcher wpm-style-' . esc_attr( $style ) . ' ' . esc_attr( $align_class ) ) ); ?>>

	<?php if ( 'dropdown' === $style ) : ?>

		<select onchange="window.location.href=this.value" aria-label="<?php esc_attr_e( 'Select language', 'wp-multilingual' ); ?>">
			<?php foreach ( $languages as $slug => $cfg ) : ?>
				<option value="<?php echo esc_url( $url_handler->switch_url( $slug ) ); ?>"
					<?php selected( $slug, $current ); ?>>
					<?php echo esc_html( isset( $cfg['label'] ) ? $cfg['label'] : strtoupper( $slug ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

	<?php else : ?>

		<ul class="wpm-lang-list">
			<?php foreach ( $languages as $slug => $cfg ) :
				$is_active = ( $slug === $current );
				$label     = isset( $cfg['label'] ) ? $cfg['label'] : strtoupper( $slug );
				$url       = $url_handler->switch_url( $slug );
			?>
				<li class="wpm-lang-item<?php echo $is_active ? ' wpm-active' : ''; ?>">
					<?php if ( $is_active ) : ?>
						<span class="wpm-lang-current" aria-current="true">
							<?php if ( $show_label ) echo esc_html( $label ); ?>
						</span>
					<?php else : ?>
						<a href="<?php echo esc_url( $url ); ?>" hreflang="<?php echo esc_attr( $slug ); ?>"
							rel="alternate" class="wpm-lang-link">
							<?php if ( $show_label ) echo esc_html( $label ); ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>

</div>
