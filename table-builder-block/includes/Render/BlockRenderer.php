<?php

namespace TableBuilder\Render;

defined( 'ABSPATH' ) || exit;

class BlockRenderer {

	/**
	 * Render static table-builder block structure in PHP.
	 */
	public static function render_table_builder( array $attributes = array(), array $row_blocks = array(), string $tbody_content = '' ): string {
		$caption      = $attributes['caption'] ?? '';
		$headers      = is_array( $attributes['headers'] ?? null ) ? $attributes['headers'] : array();
		$footers      = is_array( $attributes['footers'] ?? null ) ? $attributes['footers'] : array();
		$has_header   = ! empty( $attributes['hasHeader'] );
		$has_footer   = ! empty( $attributes['hasFooter'] );
		$show_caption = ! empty( $attributes['showCaption'] );
		$is_empty     = ! empty( $attributes['isEmpty'] );
		$block_class  = trim( (string) ( $attributes['blockClass'] ?? '' ) );
		$block_id     = (string) ( $attributes['blockID'] ?? '' );
		$col_widths   = is_array( $attributes['columnWidths'] ?? null ) ? $attributes['columnWidths'] : array();

		$settings = array(
			'enableSorting'          => ! empty( $attributes['enableSorting'] ),
			'tableConditionalFormat' => is_array( $attributes['tableConditionalFormat'] ?? null ) ? $attributes['tableConditionalFormat'] : array(),
			'mergeTableCell'         => $attributes['mergeTableCell'] ?? array(),
			'freezeSettings'         => $attributes['freezeSettings'] ?? array(),
			'blockClass'             => $block_class,
			'enableFreeze'           => ! empty( $attributes['enableFreeze'] ),
			'columnWidths'           => $col_widths,
			'rowHeights'             => is_array( $attributes['rowHeights'] ?? null ) ? $attributes['rowHeights'] : array(),
			'headerHeight'           => absint( $attributes['headerHeight'] ?? 0 ),
			'resizerEnabled'         => isset( $attributes['resizerEnabled'] ) ? (bool) $attributes['resizerEnabled'] : true,
		);

		$figure_classes = trim( 'wp-block-tablebuilder-table-builder table-builder-block ' . $block_class );
		$figure_id      = ! empty( $block_id ) ? 'block-' . sanitize_html_class( $block_id ) : '';

		$has_widths = ! empty( $col_widths ) && array_sum( array_map( 'floatval', $col_widths ) ) > 0;
		$table_style = $has_widths ? 'table-layout:fixed;width:100%;' : '';

		$rendered_tbody = '';
		if ( ! empty( $tbody_content ) ) {
			$rendered_tbody = wp_kses_post( $tbody_content );
		} elseif ( ! empty( $row_blocks ) ) {
			$rendered_tbody = self::render_table_rows( $row_blocks );
		}

		ob_start();
		?>
		<figure class="<?php echo esc_attr( $figure_classes ); ?>" data-block="tablebuilder/table-builder" data-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>"<?php echo ! empty( $figure_id ) ? ' id="' . esc_attr( $figure_id ) . '"' : ''; ?>>
			<?php if ( ! $is_empty ) : ?>
				<table class="gkit-table"<?php echo ! empty( $table_style ) ? ' style="' . esc_attr( $table_style ) . '"' : ''; ?>>
					<?php if ( $has_header ) : ?>
						<thead class="gkit-table__header">
							<tr>
								<?php foreach ( $headers as $index => $header ) : ?>
									<?php $col_style = self::get_column_style( $col_widths, $index ); ?>
									<th class="gkit-table__header-content"<?php echo ! empty( $col_style ) ? ' style="' . esc_attr( $col_style ) . '"' : ''; ?>>
										<div><?php echo wp_kses_post( $header['title'] ?? '' ); ?></div>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
					<?php endif; ?>

					<tbody class="gkit-table__body"><?php echo $rendered_tbody;?></tbody>

					<?php if ( $has_footer ) : ?>
						<tfoot class="gkit-table__footer">
							<tr>
								<?php foreach ( $footers as $index => $footer ) : ?>
									<?php $col_style = self::get_column_style( $col_widths, $index ); ?>
									<td class="gkit-table__footer-content"<?php echo ! empty( $col_style ) ? ' style="' . esc_attr( $col_style ) . '"' : ''; ?>>
										<div><?php echo wp_kses_post( $footer['title'] ?? '' ); ?></div>
									</td>
								<?php endforeach; ?>
							</tr>
						</tfoot>
					<?php endif; ?>
				</table>

				<?php if ( $show_caption ) : ?>
					<figcaption class="gkit-table__caption"><?php echo wp_kses_post( $caption ); ?></figcaption>
				<?php endif; ?>
			<?php endif; ?>
		</figure>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render row and cell inner blocks for tbody markup.
	 */
	private static function render_table_rows( array $row_blocks ): string {
		$output = '';

		foreach ( $row_blocks as $row_block ) {
			if ( ( $row_block['blockName'] ?? '' ) !== 'tablebuilder/table-builder-row' ) {
				continue;
			}

			$row_class = trim( 'wp-block-tablebuilder-table-builder-row gkit-table__body-row ' . ( $row_block['attrs']['blockClass'] ?? '' ) );
			$output   .= '<tr class="' . esc_attr( $row_class ) . '">';

			foreach ( $row_block['innerBlocks'] ?? array() as $item_block ) {
				if ( ( $item_block['blockName'] ?? '' ) !== 'tablebuilder/table-builder-item' ) {
					continue;
				}

				$item_class  = trim( 'wp-block-tablebuilder-table-builder-item gkit-table__body-content ' . ( $item_block['attrs']['blockClass'] ?? '' ) );
				$item_markup = self::render_nested_inner_blocks( $item_block['innerBlocks'] ?? array() );
				$output     .= '<td class="' . esc_attr( $item_class ) . '"><div>' . $item_markup . '</div></td>';
			}

			$output .= '</tr>';
		}

		return $output;
	}

	/**
	 * Render nested inner blocks of a cell.
	 */
	private static function render_nested_inner_blocks( array $blocks ): string {
		$output = '';

		foreach ( $blocks as $inner ) {
			$output .= render_block( $inner );
		}

		return $output;
	}

	/**
	 * Convert stored column widths into percentages for saved frontend parity.
	 */
	private static function get_column_style( array $column_widths, int $index ): string {
		if ( empty( $column_widths[ $index ] ) ) {
			return '';
		}

		$total_width = array_sum( array_map( 'floatval', $column_widths ) );
		if ( $total_width <= 0 ) {
			return '';
		}

		$percentage = ( (float) $column_widths[ $index ] / $total_width ) * 100;
		return 'width:' . round( $percentage, 4 ) . '%;';
	}
}
