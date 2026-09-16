<?php
defined('ABSPATH') || die('Access Denied');

$gallery_id = isset($settings->gallery_id) ? intval($settings->gallery_id) : 0;
// Beaver provides $id for the rendered module instance. Use it as the
// instance identity so duplicate modules never fall back to a gallery ID.
$node_id = isset($id) && '' !== $id ? $id : ( isset($module->node) ? $module->node : '' );

if ( class_exists('FLBuilderModel') && FLBuilderModel::is_builder_active() ) {
  if ( 0 === $gallery_id ) {
    // A new module has no gallery data to mount yet. Rendering an empty React
    // gallery under the selector produces the misleading “There is not data.”
    // preview, so show only the intended Beaver placeholder.
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo REACG_Builder_Common::get_builder_placeholder( $gallery_id, $node_id );
  } else {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo REACG_Builder_Common::render_builder_editor($gallery_id, $node_id);
  }
} else {
  if ( $gallery_id !== 0 ) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo REACGLibrary::get_rest_routs($gallery_id, FALSE, $node_id);
  }
}
