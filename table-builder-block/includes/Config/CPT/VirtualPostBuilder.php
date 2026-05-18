<?php

namespace TableBuilder\Config\CPT;

defined('ABSPATH') || exit;

use WP_Post;
use WP_Query;

class VirtualPostBuilder
{
	private const VIRTUAL_ID_BASE = -1000000;

	private InlineTableScanner $scanner;

	public function __construct(InlineTableScanner $scanner)
	{
		$this->scanner = $scanner;
	}

	public function inject_inline_block_tables(array $posts, WP_Query $query): array
	{
		if (! $this->is_cpt_admin_list_query($query)) {
			return $posts;
		}

		$inline_tables = $this->scanner->get_all_inline_block_tables();

		if (empty($inline_tables)) {
			return $posts;
		}

		$grouped = $this->group_inline_tables_by_source($inline_tables);
		foreach (array_values($grouped) as $index => $group) {
			$posts[] = $this->build_virtual_post($group, $index);
		}

		return $posts;
	}

	public function get_virtual_source_id_map(): array
	{
		$map = array();
		$grouped = $this->group_inline_tables_by_source($this->scanner->get_all_inline_block_tables());

		foreach (array_values($grouped) as $index => $group) {
			$map[self::VIRTUAL_ID_BASE - $index] = (int) $group['source_id'];
		}

		return $map;
	}

	public function render_shortcode_input(string $shortcode): void
	{
		echo '<div class="tablekit-shortcode-wrap" style="position:relative;">';
		printf(
			'<input type="text" class="tablekit-shortcode-field" readonly value="%s" style="width:100%%;padding-right:32px;" />',
			esc_attr($shortcode)
		);
		$copy_label = esc_attr__('Copy shortcode', 'table-builder-block');
		printf(
			'<button type="button" class="tablekit-copy-shortcode" data-copy-text="%s" aria-label="%s" title="%s" style="position:absolute;right:6px;top:50%%;transform:translateY(-50%%);border:0;background:transparent;color:#2271b1;cursor:pointer;padding:0;line-height:1;">'
				. '<span class="tablekit-copy-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><rect x="7" y="5" width="9" height="11" rx="2"></rect><path d="M4 12.5V4a2 2 0 0 1 2-2h7.5"></path></svg></span>'
				. '</button>',
			esc_attr($shortcode),
			$copy_label,
			$copy_label
		);
		echo '</div>';
	}

	public function render_child_shortcode_panel(array $blocks, int $post_id, int $source_id): void
	{
		$panel_id    = 'tablekit-children-' . absint($post_id);
		$count       = count($blocks);
		$label_open  = esc_js(sprintf(__('&#9654; Child shortcodes (%d)', 'table-builder-block'), $count));
		$label_close = esc_js(sprintf(__('&#9660; Child shortcodes (%d)', 'table-builder-block'), $count));
	?>
		<div class="tablekit-child-shortcode-toggle-wrap">
			<button
				type="button"
				onclick="(function(btn){var p=document.getElementById('<?php echo esc_js($panel_id); ?>');var o=p.style.display!=='none';p.style.display=o?'none':'block';btn.innerHTML=o?'<?php echo $label_open; ?>':'<?php echo $label_close; ?>';})(this)"
				class="tablekit-child-shortcode-toggle">
				<?php echo wp_kses_post(sprintf(__('&#9654; Child shortcodes (%d)', 'table-builder-block'), $count)); ?>
			</button>
		</div>
		<div id="<?php echo esc_attr($panel_id); ?>" class="tablekit-child-shortcode-panel" style="display:none;">
			<?php foreach ($blocks as $block) : ?>
				<div class="tablekit-child-shortcode-item">
					<span class="tablekit-child-shortcode-label">
						<?php echo esc_html(TableCPT::get_block_label($block['block_name'])); ?>
					</span>
					<?php $child_shortcode = $this->build_child_shortcode($block, $source_id); ?>
					<?php $this->render_shortcode_input($child_shortcode); ?>
				</div>
			<?php endforeach; ?>
		</div>
<?php
	}

	private function is_cpt_admin_list_query(WP_Query $query): bool
	{
		return is_admin()
			&& $query->is_main_query()
			&& TableCPT::POST_TYPE === $query->get('post_type');
	}

	private function group_inline_tables_by_source(array $inline_tables): array
	{
		$grouped = array();

		foreach ($inline_tables as $table) {
			$sid = (int) $table['source_id'];

			if (! isset($grouped[$sid])) {
				$grouped[$sid] = array(
					'source_id'     => $sid,
					'source_title'  => $table['source_title'],
					'source_type'   => $table['source_type'],
					'source_author' => $table['source_author'],
					'source_date'   => $table['source_date'],
					'blocks'        => array(),
				);
			}

			$grouped[$sid]['blocks'][] = array(
				'block_name'   => $table['block_name'],
				'block_id'     => $table['block_id'],
				'shortcode_id' => $table['shortcode_id'],
			);
		}

		return $grouped;
	}

	private function build_virtual_post(array $group, int $index): WP_Post
	{
		$block_count = count($group['blocks']);

		/* translators: %2$s = source post title. */
		$title = sprintf(
			_n('Table in: %2$s', 'Tables in: %2$s', $block_count, 'table-builder-block'),
			$block_count,
			$group['source_title']
		);

		$virtual_post = new WP_Post(
			(object) array(
				'ID'                => self::VIRTUAL_ID_BASE - $index,
				'post_title'        => $title,
				'post_type'         => TableCPT::POST_TYPE,
				'post_status'       => 'publish',
				'post_author'       => 0,
				'post_date'         => $group['source_date'],
				'post_date_gmt'     => get_gmt_from_date($group['source_date']),
				'post_content'      => '',
				'post_excerpt'      => '',
				'post_name'         => '',
				'post_modified'     => $group['source_date'],
				'post_modified_gmt' => get_gmt_from_date($group['source_date']),
				'comment_status'    => 'closed',
				'ping_status'       => 'closed',
				'guid'              => '',
				'menu_order'        => 0,
				'post_mime_type'    => '',
				'comment_count'     => 0,
				'filter'            => 'raw',
			)
		);

		$virtual_post->tablekit_is_virtual    = true;
		$virtual_post->tablekit_source_id     = $group['source_id'];
		$virtual_post->tablekit_source_title  = $group['source_title'];
		$virtual_post->tablekit_source_type   = $group['source_type'];
		$virtual_post->tablekit_source_author = $group['source_author'];
		$virtual_post->tablekit_blocks        = $group['blocks'];

		return $virtual_post;
	}

	private function build_child_shortcode(array $block, int $source_id): string
	{
		if ('' !== $block['shortcode_id']) {
			return sprintf('[tableKit id="%s"]', $block['shortcode_id']);
		}

		return sprintf('[tableKit post_id="%d"]', $source_id);
	}
}
