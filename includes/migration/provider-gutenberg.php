<?php

defined('ABSPATH') || die('Access Denied');

class REACG_Migration_Provider_Gutenberg implements REACG_Migration_Provider_Interface {
  public function get_key() {
    return 'gutenberg';
  }

  public function get_label() {
    return __('Gutenberg Gallery', 'regallery');
  }

  public function is_available() {
    return function_exists('parse_blocks');
  }

  public function list_galleries($args = []) {
    $search = !empty($args['search']) ? sanitize_text_field($args['search']) : '';

    $query = new WP_Query([
      'post_type' => get_post_types(['public' => true]),
      'post_status' => ['publish', 'draft', 'private'],
      // For Gutenberg source discovery we need a full scan; pagination is handled in JS table.
      'posts_per_page' => -1,
      's' => $search,
      'fields' => 'ids',
      'no_found_rows' => true,
    ]);

    $items = [];
    foreach ($query->posts as $post_id) {
      $post = get_post($post_id);
      if (!$post || empty($post->post_content)) {
        continue;
      }

      $gallery_blocks = $this->extract_gallery_blocks(parse_blocks($post->post_content));
      if (empty($gallery_blocks)) {
        $gallery_shortcodes = $this->extract_gallery_shortcodes($post->post_content);
        if (empty($gallery_shortcodes)) {
          continue;
        }
      }

      foreach ($gallery_blocks as $index => $gallery_block) {
        $ids = $this->extract_ids_from_gallery_block($gallery_block);
        $items[] = [
          'id' => $post_id . ':' . $index,
          'title' => (get_the_title($post_id) ?: __('(no title)', 'regallery')) . ' #' . ($index + 1),
          'image_count' => count($ids),
        ];
      }

      $gallery_shortcodes = $this->extract_gallery_shortcodes($post->post_content);
      foreach ($gallery_shortcodes as $index => $shortcode_gallery) {
        $items[] = [
          'id' => $post_id . ':s:' . $index,
          'title' => (get_the_title($post_id) ?: __('(no title)', 'regallery')) . ' #' . ($index + 1) . ' [shortcode]',
          'image_count' => count($shortcode_gallery['ids']),
        ];
      }
    }

    return [
      'items' => $items,
      'total' => count($items),
      'page' => 1,
      'per_page' => count($items),
    ];
  }

  public function get_gallery($external_id) {
    $parsed = $this->parse_external_id($external_id);
    $post_id = $parsed['post_id'];
    $gallery_index = $parsed['index'];
    $gallery_type = $parsed['type'];

    if (!$post_id) {
      return new WP_Error('reacg_migration_invalid_gallery', __('Invalid Gutenberg gallery identifier.', 'regallery'));
    }

    $post = get_post($post_id);
    if (!$post || empty($post->post_content)) {
      return new WP_Error('reacg_migration_not_found', __('Gutenberg source post not found.', 'regallery'));
    }

    if ($gallery_type === 'shortcode') {
      $shortcode_galleries = $this->extract_gallery_shortcodes($post->post_content);
      if (!isset($shortcode_galleries[$gallery_index])) {
        return new WP_Error('reacg_migration_not_found', __('Gutenberg gallery shortcode not found.', 'regallery'));
      }

      $shortcode_gallery = $shortcode_galleries[$gallery_index];
      $items = [];
      foreach ((array) $shortcode_gallery['ids'] as $id) {
        $attachment_id = intval($id);
        $item = [
          'attachment_id' => $attachment_id,
        ];

        $attachment_caption = wp_get_attachment_caption($attachment_id);
        if (is_string($attachment_caption) && trim($attachment_caption) !== '') {
          $item['caption'] = sanitize_text_field($attachment_caption);
        }

        $attachment_title = get_the_title($attachment_id);
        if (is_string($attachment_title) && trim($attachment_title) !== '') {
          $item['title'] = sanitize_text_field($attachment_title);
        }

        $items[] = $item;
      }

      return [
        'id' => $post_id . ':s:' . $gallery_index,
        'title' => (get_the_title($post_id) ?: __('Imported Gutenberg Gallery', 'regallery')) . ' #' . ($gallery_index + 1),
        'items' => $items,
        'settings' => $this->map_shortcode_gallery_settings($shortcode_gallery, $items),
      ];
    }

    $gallery_blocks = $this->extract_gallery_blocks(parse_blocks($post->post_content));
    if (!isset($gallery_blocks[$gallery_index])) {
      return new WP_Error('reacg_migration_not_found', __('Gutenberg gallery block not found.', 'regallery'));
    }

    $gallery_block = $gallery_blocks[$gallery_index];
    $items = $this->extract_items_from_gallery_block($gallery_block);

    // Fallback for legacy gallery markup where only IDs exist.
    if (empty($items)) {
      $ids = $this->extract_ids_from_gallery_block($gallery_block);
      foreach ($ids as $id) {
        $items[] = ['attachment_id' => intval($id)];
      }
    }

    return [
      'id' => $post_id . ':' . $gallery_index,
      'title' => (get_the_title($post_id) ?: __('Imported Gutenberg Gallery', 'regallery')) . ' #' . ($gallery_index + 1),
      'items' => $items,
      'settings' => $this->map_settings($gallery_block, $items),
    ];
  }

  private function extract_gallery_blocks($blocks, $visited_refs = []) {
    $result = [];

    foreach ((array) $blocks as $block) {
      if (!empty($block['blockName']) && $block['blockName'] === 'core/gallery') {
        $result[] = $block;
      }

      // Resolve reusable/synced blocks that may contain gallery blocks.
      if (!empty($block['blockName']) && $block['blockName'] === 'core/block') {
        $ref = !empty($block['attrs']['ref']) ? intval($block['attrs']['ref']) : 0;
        if ($ref > 0 && !in_array($ref, $visited_refs, true)) {
          $ref_post = get_post($ref);
          if ($ref_post && !empty($ref_post->post_content)) {
            $nested = $this->extract_gallery_blocks(
              parse_blocks($ref_post->post_content),
              array_merge($visited_refs, [$ref])
            );
            if (!empty($nested)) {
              $result = array_merge($result, $nested);
            }
          }
        }
      }

      if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
        $result = array_merge($result, $this->extract_gallery_blocks($block['innerBlocks'], $visited_refs));
      }
    }

    return $result;
  }

  private function extract_gallery_shortcodes($content) {
    $result = [];

    if (!is_string($content) || $content === '') {
      return $result;
    }

    $pattern = get_shortcode_regex(['gallery']);
    if (!preg_match_all('/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER)) {
      return $result;
    }

    foreach ($matches as $match) {
      $tag = isset($match[2]) ? strtolower((string) $match[2]) : '';
      if ($tag !== 'gallery') {
        continue;
      }

      $attrs = shortcode_parse_atts(isset($match[3]) ? $match[3] : '');
      if (!is_array($attrs)) {
        $attrs = [];
      }

      $ids = [];
      if (!empty($attrs['ids'])) {
        $ids = array_filter(array_map('intval', array_map('trim', explode(',', (string) $attrs['ids']))));
      }

      if (empty($ids) && !empty($attrs['include'])) {
        $ids = array_filter(array_map('intval', array_map('trim', explode(',', (string) $attrs['include']))));
      }

      if (!empty($ids)) {
        $result[] = [
          'ids' => array_values(array_unique($ids)),
          'attrs' => $attrs,
        ];
      }
    }

    return $result;
  }

  private function parse_external_id($external_id) {
    $parts = explode(':', (string) $external_id);

    $post_id = !empty($parts[0]) ? intval($parts[0]) : 0;
    $type = 'block';
    $index = 0;

    if (isset($parts[1]) && $parts[1] === 's') {
      $type = 'shortcode';
      $index = isset($parts[2]) ? intval($parts[2]) : 0;
    } else {
      $index = isset($parts[1]) ? intval($parts[1]) : 0;
    }

    return [
      'post_id' => $post_id,
      'type' => $type,
      'index' => max(0, $index),
    ];
  }

  private function map_shortcode_gallery_settings($shortcode_gallery, $items) {
    $attrs = !empty($shortcode_gallery['attrs']) && is_array($shortcode_gallery['attrs'])
      ? $shortcode_gallery['attrs']
      : [];

    $columns = !empty($attrs['columns']) ? max(1, min(8, intval($attrs['columns']))) : 0;
    $link = !empty($attrs['link']) ? sanitize_key((string) $attrs['link']) : '';

    $overrides = [
      'type' => 'thumbnails',
      'thumbnails' => [
        'fillContainer' => true,
      ],
      'general' => [],
    ];

    if ($columns > 0) {
      $overrides['thumbnails']['columns'] = $columns;
    }

    if ($link === 'none') {
      $overrides['general']['clickAction'] = 'none';
    } elseif ($link === 'file') {
      $overrides['general']['clickAction'] = 'lightbox';
    } elseif ($link === 'post') {
      $overrides['general']['clickAction'] = 'url';
    }

    // Forced Gutenberg migration defaults.
    $overrides['thumbnails']['showTitle'] = false;
    $overrides['thumbnails']['showCaption'] = true;
    $overrides['thumbnails']['captionSource'] = 'caption';
    $overrides['thumbnails']['captionVisibility'] = 'alwaysShown';
    $overrides['thumbnails']['captionPosition'] = 'bottom';
    $overrides['thumbnails']['titleAlignment'] = 'center';

    return $this->cleanup_overrides($overrides);
  }

  private function extract_ids_from_gallery_block($block) {
    $ids = [];

    if (!empty($block['attrs']['ids']) && is_array($block['attrs']['ids'])) {
      $ids = array_merge($ids, array_map('intval', $block['attrs']['ids']));
    }

    if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
      foreach ($block['innerBlocks'] as $inner_block) {
        if (!empty($inner_block['blockName']) && $inner_block['blockName'] === 'core/image' && !empty($inner_block['attrs']['id'])) {
          $ids[] = intval($inner_block['attrs']['id']);
        }
      }
    }

    $ids = array_filter($ids);
    return array_values(array_unique($ids));
  }

  private function extract_items_from_gallery_block($gallery_block) {
    $items = [];

    if (!empty($gallery_block['innerBlocks']) && is_array($gallery_block['innerBlocks'])) {
      foreach ($gallery_block['innerBlocks'] as $inner_block) {
        if (empty($inner_block['blockName']) || $inner_block['blockName'] !== 'core/image') {
          continue;
        }

        $attrs = !empty($inner_block['attrs']) && is_array($inner_block['attrs'])
          ? $inner_block['attrs']
          : [];

        $item = [];

        if (!empty($attrs['id'])) {
          $item['attachment_id'] = intval($attrs['id']);
        }

        if (!empty($attrs['url'])) {
          $item['url'] = esc_url_raw($attrs['url']);
        }

        if (!empty($attrs['alt'])) {
          $item['alt'] = sanitize_text_field($attrs['alt']);
        }

        if (!empty($attrs['title'])) {
          $item['title'] = sanitize_text_field($attrs['title']);
        }

        if (!empty($attrs['caption'])) {
          $item['caption'] = wp_strip_all_tags((string) $attrs['caption']);
        }

        if (empty($item['caption']) && !empty($inner_block['innerHTML']) && is_string($inner_block['innerHTML'])) {
          if (preg_match('/<figcaption[^>]*>(.*?)<\/figcaption>/is', $inner_block['innerHTML'], $matches)) {
            $rendered_caption = wp_strip_all_tags($matches[1]);
            if (is_string($rendered_caption) && trim($rendered_caption) !== '') {
              $item['caption'] = sanitize_text_field($rendered_caption);
            }
          }
        }

        // Gutenberg may render captions from attachment caption even if block attrs.caption is empty.
        if (empty($item['caption']) && !empty($item['attachment_id'])) {
          $attachment_caption = wp_get_attachment_caption(intval($item['attachment_id']));
          if (is_string($attachment_caption) && trim($attachment_caption) !== '') {
            $item['caption'] = sanitize_text_field($attachment_caption);
          }
        }

        if (!empty($attrs['href'])) {
          $item['action_url'] = esc_url_raw($attrs['href']);
        }

        if (!empty($item['attachment_id']) || !empty($item['url'])) {
          $items[] = $item;
        }
      }
    }

    return $items;
  }

  private function map_settings($gallery_block, $items) {
    $attrs = !empty($gallery_block['attrs']) && is_array($gallery_block['attrs'])
      ? $gallery_block['attrs']
      : [];
    $style = !empty($attrs['style']) && is_array($attrs['style'])
      ? $attrs['style']
      : [];

    $columns = !empty($attrs['columns']) ? intval($attrs['columns']) : 0;
    $aspect_ratio = $this->extract_gallery_aspect_ratio($attrs, $style);
    $is_original_aspect = $this->is_original_aspect_ratio($aspect_ratio);
    $is_crop_disabled = $this->is_crop_explicitly_disabled($attrs);

    if ($is_crop_disabled && $is_original_aspect) {
      $layout_type = 'masonry';
    } else {
      $layout_type = 'thumbnails';
    }

    $overrides = [
      'type' => $layout_type,
      $layout_type => [],
      'general' => [],
    ];
    $layout_ref =& $overrides[$layout_type];

    if ($columns > 0) {
      $layout_ref['columns'] = max(1, min(8, $columns));
    }

    // Gutenberg gallery should map to thumbnails with cover behavior.
    if ($layout_type === 'thumbnails') {
      $overrides['thumbnails']['fillContainer'] = true;
    }

    // Gallery Aspect ratio -> thumbnails.aspectRatio
    $normalized_aspect = $this->normalize_aspect_ratio($aspect_ratio);
    if ($layout_type === 'thumbnails' && !$is_crop_disabled && $is_original_aspect) {
      $overrides['thumbnails']['aspectRatio'] = '0.66';
    } elseif ($layout_type === 'thumbnails' && $normalized_aspect !== null) {
      $overrides['thumbnails']['aspectRatio'] = $normalized_aspect;
    }

    // Gallery Background -> Container background color.
    $background_color = $this->extract_color(
      $this->read_nested($style, ['color', 'background'])
    );
    if ($background_color !== null) {
      $layout_ref['backgroundColor'] = $background_color;
    }

    // Gallery block spacing -> Items spacing.
    $block_spacing = $this->extract_css_size(
      $this->read_nested($style, ['spacing', 'blockGap'])
    );
    if ($block_spacing !== null) {
      $spacing_value = max(0, intval(round($block_spacing)));
      if ($layout_type === 'thumbnails') {
        $layout_ref['itemBorder'] = $spacing_value;
      } else {
        // Non-thumbnails layouts do not use itemBorder in options.
        $layout_ref['padding'] = $spacing_value;
      }
    }

    // Gallery border -> Container padding.
    $container_border = $this->extract_css_size(
      $this->read_nested($style, ['border', 'width'])
    );
    if ($container_border !== null) {
      $layout_ref['containerPadding'] = max(0, intval(round($container_border)));
    }

    $container_padding = $this->extract_css_size(
      $this->read_nested($style, ['spacing', 'padding'])
    );
    if ($container_padding !== null) {
      $layout_ref['containerPadding'] = max(0, intval(round($container_padding)));
    }

    // Image border/radius from image block style.
    $image_style = $this->extract_image_style($gallery_block);
    if (!empty($image_style['border'])) {
      $layout_ref['padding'] = max(0, intval(round($image_style['border'])));
    }

    if (!empty($image_style['color'])) {
        $layout_ref['paddingColor'] = $image_style['color'];
    }

    if (!empty($image_style['radius']) || $image_style['radius'] === 0.0) {
      $radius_percent = $this->normalize_radius_percent(
        $image_style['radius'],
        $image_style['radius_unit'],
        $image_style['radius_reference_size']
      );
      $radius = max(0, min(100, intval(round($radius_percent))));
      $layout_ref['borderRadius'] = $radius;
    }

    $title_position = $this->detect_gutenberg_title_position($gallery_block);

    // Forced Gutenberg migration defaults.
    $layout_ref['showCaption'] = true;
    $layout_ref['captionSource'] = 'caption';
    $layout_ref['captionVisibility'] = 'alwaysShown';
    $layout_ref['captionPosition'] = 'bottom';
    $layout_ref['titleAlignment'] = 'center';

    $layout_ref['showTitle'] = false;
    $layout_ref['titleVisibility'] = 'onHover';

    $link_setting = '';
    if (!empty($attrs['linkTo'])) {
      $link_setting = sanitize_key((string) $attrs['linkTo']);
    } elseif (!empty($attrs['linkDestination'])) {
      $link_setting = sanitize_key((string) $attrs['linkDestination']);
    }

    $has_custom_links = false;
    foreach ((array) $items as $item) {
      if (!empty($item['action_url'])) {
        $has_custom_links = true;
        break;
      }
    }

    if ($link_setting === 'none') {
      $overrides['general']['clickAction'] = 'none';
    } elseif ($link_setting === 'post' || $link_setting === 'custom' || $has_custom_links) {
      $overrides['general']['clickAction'] = 'url';
    } elseif (in_array($link_setting, ['media', 'file', 'attachment'], true)) {
      $overrides['general']['clickAction'] = 'lightbox';
    }

    if (!empty($attrs['sizeSlug'])) {
      $size_slug = sanitize_key((string) $attrs['sizeSlug']);
      if ($size_slug === 'thumbnail') {
        $overrides['lightbox']['isFullscreen'] = false;
        $overrides['lightbox']['width'] = 150;
        $overrides['lightbox']['height'] = 150;
      }
      if ($size_slug === 'medium') {
        $overrides['lightbox']['isFullscreen'] = false;
        $overrides['lightbox']['width'] = 300;
        $overrides['lightbox']['height'] = 300;
      }
      if ($size_slug === 'large') {
        $overrides['lightbox']['isFullscreen'] = false;
        $overrides['lightbox']['width'] = 1024;
        $overrides['lightbox']['height'] = 1024;
      }
    }

    return $this->cleanup_overrides($overrides);
  }

  private function detect_cropped($attrs) {
    if (isset($attrs['isCropped'])) {
      return $this->to_bool_flag($attrs['isCropped']);
    }

    if (isset($attrs['imageCrop'])) {
      return $this->to_bool_flag($attrs['imageCrop']);
    }

    if (isset($attrs['crop'])) {
      return $this->to_bool_flag($attrs['crop']);
    }

    if (!empty($attrs['className']) && strpos((string) $attrs['className'], 'is-cropped') !== false) {
      if (strpos((string) $attrs['className'], 'is-not-cropped') !== false) {
        return false;
      }
      return true;
    }

    return false;
  }

  private function is_crop_explicitly_enabled($attrs) {
    foreach (['isCropped', 'imageCrop', 'crop'] as $key) {
      if (array_key_exists($key, (array) $attrs)) {
        return $this->to_bool_flag($attrs[$key]);
      }
    }

    $class_name = !empty($attrs['className']) ? (string) $attrs['className'] : '';
    if ($class_name !== '') {
      if (preg_match('/(^|\s)is-not-cropped(\s|$)/', $class_name)) {
        return false;
      }
      if (preg_match('/(^|\s)is-cropped(\s|$)/', $class_name)) {
        return true;
      }
    }

    // No explicit crop flag detected.
    return false;
  }

  private function is_crop_explicitly_disabled($attrs) {
    foreach (['isCropped', 'imageCrop', 'crop'] as $key) {
      if (array_key_exists($key, (array) $attrs)) {
        return !$this->to_bool_flag($attrs[$key]);
      }
    }

    $class_name = !empty($attrs['className']) ? (string) $attrs['className'] : '';
    if ($class_name !== '') {
      if (preg_match('/(^|\s)is-not-cropped(\s|$)/', $class_name)) {
        return true;
      }
      if (preg_match('/(^|\s)is-cropped(\s|$)/', $class_name)) {
        return false;
      }
    }

    // No explicit crop flag detected.
    return false;
  }

  private function to_bool_flag($value) {
    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return intval($value) === 1;
    }

    if (is_string($value)) {
      $normalized = strtolower(trim($value));
      if (in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
        return true;
      }
      if (in_array($normalized, ['0', 'false', 'no', 'off', 'disabled', ''], true)) {
        return false;
      }
    }

    return (bool) $value;
  }

  private function read_nested($array, $path) {
    $value = $array;
    foreach ((array) $path as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) {
        return null;
      }
      $value = $value[$segment];
    }

    return $value;
  }

  private function extract_css_size($value) {
    if (is_array($value)) {
      foreach (['top', 'right', 'bottom', 'left', 'topLeft', 'topRight', 'bottomRight', 'bottomLeft'] as $edge) {
        if (isset($value[$edge])) {
          $edge_size = $this->extract_css_size($value[$edge]);
          if ($edge_size !== null) {
            return $edge_size;
          }
        }
      }
      return null;
    }

    if (is_numeric($value)) {
      return floatval($value);
    }

    if (!is_string($value)) {
      return null;
    }

    $value = trim($value);
    if ($value === '') {
      return null;
    }

    if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $value, $matches)) {
      return floatval($matches[1]);
    }

    return null;
  }

  private function extract_color($value) {
    if (!is_string($value)) {
      return null;
    }

    $value = trim($value);
    if ($value === '') {
      return null;
    }

    // Keep CSS colors or CSS vars as-is.
    if (preg_match('/^(#|rgb\(|rgba\(|hsl\(|hsla\(|var\(|--)/i', $value)) {
      return sanitize_text_field($value);
    }

    // Gutenberg can store palette references as var:preset|color|slug.
    if (strpos($value, 'var:preset|color|') === 0) {
      $slug = sanitize_key(str_replace('var:preset|color|', '', $value));
      if ($slug !== '') {
        return 'var(--wp--preset--color--' . $slug . ')';
      }
    }

    return null;
  }

  private function normalize_aspect_ratio($value) {
    $value = trim((string) $value);
    if ($value === '') {
      return null;
    }

    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*[\/:]\s*([0-9]+(?:\.[0-9]+)?)$/', $value, $matches)) {
      $left = floatval($matches[1]);
      $right = floatval($matches[2]);
      if ($left > 0 && $right > 0) {
        return (string) round($left / $right, 2);
      }
      return null;
    }

    if (is_numeric($value) && floatval($value) > 0) {
      return (string) round(floatval($value), 2);
    }

    return null;
  }

  private function is_original_aspect_ratio($value) {
    $value = strtolower(trim((string) $value));

    if (
      $value === '' ||
      $value === 'original' ||
      $value === 'original-size' ||
      $value === 'natural' ||
      $value === 'default' ||
      $value === 'none' ||
      $value === 'auto' ||
      $value === 'inherit'
    ) {
      return true;
    }

    return false;
  }

  private function extract_gallery_aspect_ratio($attrs, $style) {
    if (!empty($attrs['aspectRatio'])) {
      return (string) $attrs['aspectRatio'];
    }

    $style_aspect_ratio = $this->read_nested($style, ['dimensions', 'aspectRatio']);
    if (!empty($style_aspect_ratio)) {
      return (string) $style_aspect_ratio;
    }

    $class_name = !empty($attrs['className']) ? (string) $attrs['className'] : '';
    if ($class_name !== '') {
      // Common Gutenberg class format, e.g. "is-aspect-ratio-16-9".
      if (preg_match('/is-aspect-ratio-([0-9]+)-([0-9]+)/', $class_name, $matches)) {
        return $matches[1] . '/' . $matches[2];
      }

      // Defensive fallback for custom block class conventions.
      if (preg_match('/aspect(?:-ratio)?-([0-9]+)-([0-9]+)/', $class_name, $matches)) {
        return $matches[1] . '/' . $matches[2];
      }
    }

    return '';
  }

  private function extract_image_style($gallery_block) {
    $result = [
      'border' => null,
      'color' => null,
      'radius' => null,
      'radius_unit' => null,
      'radius_reference_size' => null,
    ];

    if (empty($gallery_block['innerBlocks']) || !is_array($gallery_block['innerBlocks'])) {
      return $result;
    }

    foreach ($gallery_block['innerBlocks'] as $inner_block) {
      if (empty($inner_block['blockName']) || $inner_block['blockName'] !== 'core/image') {
        continue;
      }

      $attrs = !empty($inner_block['attrs']) && is_array($inner_block['attrs'])
        ? $inner_block['attrs']
        : [];
      $style = !empty($attrs['style']) && is_array($attrs['style'])
        ? $attrs['style']
        : [];

      if ($result['radius_reference_size'] === null) {
        $result['radius_reference_size'] = $this->extract_image_reference_size($attrs);
      }

      if ($result['border'] === null) {
        $result['border'] = $this->extract_css_size($this->read_nested($style, ['border', 'width']));
      }
      if ($result['color'] === null) {
        $result['color'] = $this->extract_color($this->read_nested($style, ['border', 'color']));
      }
      if ($result['radius'] === null) {
        $radius_data = $this->extract_radius_value($this->read_nested($style, ['border', 'radius']));
        if ($radius_data !== null) {
          $result['radius'] = $radius_data['value'];
          $result['radius_unit'] = $radius_data['unit'];
        }
      }
      if ($result['radius'] === null) {
        // Some Gutenberg versions store corner radius in separate keys.
        $radius_data = $this->extract_radius_value([
          'topLeft' => $this->read_nested($style, ['border', 'topLeftRadius']),
          'topRight' => $this->read_nested($style, ['border', 'topRightRadius']),
          'bottomRight' => $this->read_nested($style, ['border', 'bottomRightRadius']),
          'bottomLeft' => $this->read_nested($style, ['border', 'bottomLeftRadius']),
        ]);
        if ($radius_data !== null) {
          $result['radius'] = $radius_data['value'];
          $result['radius_unit'] = $radius_data['unit'];
        }
      }

      if ($result['border'] !== null && $result['radius'] !== null && $result['color'] !== null) {
        break;
      }
    }

    return $result;
  }

  private function extract_radius_value($value) {
    if (is_array($value)) {
      foreach (['topLeft', 'topRight', 'bottomRight', 'bottomLeft', 'top', 'right', 'bottom', 'left'] as $edge) {
        if (isset($value[$edge])) {
          $edge_value = $this->extract_radius_value($value[$edge]);
          if ($edge_value !== null) {
            return $edge_value;
          }
        }
      }
      return null;
    }

    if (is_numeric($value)) {
      return [
        'value' => floatval($value),
        'unit' => 'px',
      ];
    }

    if (!is_string($value)) {
      return null;
    }

    $value = trim($value);
    if ($value === '') {
      return null;
    }

    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*%$/', $value, $matches)) {
      return [
        'value' => floatval($matches[1]),
        'unit' => 'percent',
      ];
    }

    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*px$/i', $value, $matches)) {
      return [
        'value' => floatval($matches[1]),
        'unit' => 'px',
      ];
    }

    if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $value, $matches)) {
      return [
        'value' => floatval($matches[1]),
        'unit' => 'px',
      ];
    }

    return null;
  }

  private function normalize_radius_percent($radius_value, $radius_unit, $reference_size) {
    $radius_value = floatval($radius_value);
    $radius_unit = strtolower(trim((string) $radius_unit));
    $reference_size = floatval($reference_size);

    if ($radius_unit === 'percent') {
      return $radius_value;
    }

    if ($reference_size > 0) {
      return ($radius_value / $reference_size) * 100;
    }

    // Fallback when source dimensions are unavailable.
    return $radius_value;
  }

  private function extract_image_reference_size($image_attrs) {
    $width = isset($image_attrs['width']) ? floatval($image_attrs['width']) : 0.0;
    $height = isset($image_attrs['height']) ? floatval($image_attrs['height']) : 0.0;

    if ($width > 0 && $height > 0) {
      return min($width, $height);
    }

    if ($width > 0) {
      return $width;
    }

    if ($height > 0) {
      return $height;
    }

    if (!empty($image_attrs['id'])) {
      $meta = wp_get_attachment_metadata(intval($image_attrs['id']));
      if (is_array($meta)) {
        $meta_width = !empty($meta['width']) ? floatval($meta['width']) : 0.0;
        $meta_height = !empty($meta['height']) ? floatval($meta['height']) : 0.0;
        if ($meta_width > 0 && $meta_height > 0) {
          return min($meta_width, $meta_height);
        }
        if ($meta_width > 0) {
          return $meta_width;
        }
        if ($meta_height > 0) {
          return $meta_height;
        }
      }
    }

    return 0.0;
  }

  private function detect_gutenberg_title_visibility($attrs, $style, $has_caption, $has_rendered_captions, $has_item_titles) {
    // Explicit hide flags should win.
    if (isset($attrs['showCaption']) && !$attrs['showCaption']) {
      return false;
    }

    if (isset($attrs['showCaptions']) && !$attrs['showCaptions']) {
      return false;
    }

    if (isset($attrs['displayCaptions']) && !$attrs['displayCaptions']) {
      return false;
    }

    if (isset($attrs['captionDisplay']) && strtolower((string) $attrs['captionDisplay']) === 'none') {
      return false;
    }

    $caption_display_style = $this->read_nested($style, ['elements', 'caption', 'display']);
    if (is_string($caption_display_style) && strtolower($caption_display_style) === 'none') {
      return false;
    }

    foreach (['showTitle', 'showTitles', 'showImageTitle', 'showImageTitles'] as $key) {
      if (isset($attrs[$key])) {
        return (bool) $attrs[$key];
      }
    }

    if (isset($attrs['showCaption'])) {
      return (bool) $attrs['showCaption'];
    }

    if (isset($attrs['showCaptions'])) {
      return (bool) $attrs['showCaptions'];
    }

    if (isset($attrs['displayCaptions'])) {
      return (bool) $attrs['displayCaptions'];
    }

    if (isset($attrs['captionDisplay'])) {
      return strtolower((string) $attrs['captionDisplay']) !== 'none';
    }

    $class_name = !empty($attrs['className']) ? (string) $attrs['className'] : '';
    if ($class_name !== '') {
      if (strpos($class_name, 'has-caption') !== false || strpos($class_name, 'has-captions') !== false) {
        return true;
      }
      if (strpos($class_name, 'show-caption') !== false || strpos($class_name, 'show-captions') !== false) {
        return true;
      }
      if (strpos($class_name, 'hide-caption') !== false || strpos($class_name, 'no-caption') !== false) {
        return false;
      }
    }

    // If caption/title markup is rendered or title text exists on image blocks,
    // prefer visible titles in migration.
    if ($has_rendered_captions || $has_item_titles) {
      return true;
    }

    return (bool) $has_caption;
  }

  private function has_rendered_captions($gallery_block) {
    if (!empty($gallery_block['innerHTML']) && is_string($gallery_block['innerHTML'])) {
      if (stripos($gallery_block['innerHTML'], '<figcaption') !== false) {
        return true;
      }
    }

    if (!empty($gallery_block['innerBlocks']) && is_array($gallery_block['innerBlocks'])) {
      foreach ($gallery_block['innerBlocks'] as $inner_block) {
        if (!empty($inner_block['innerHTML']) && is_string($inner_block['innerHTML'])) {
          if (stripos($inner_block['innerHTML'], '<figcaption') !== false) {
            return true;
          }
        }
      }
    }

    return false;
  }

  private function detect_gutenberg_title_position($gallery_block) {
    $modes = [];
    $has_explicit_border_width = false;

    $gallery_attrs = !empty($gallery_block['attrs']) && is_array($gallery_block['attrs'])
      ? $gallery_block['attrs']
      : [];
    $gallery_style = !empty($gallery_attrs['style']) && is_array($gallery_attrs['style'])
      ? $gallery_attrs['style']
      : [];

    foreach ($this->collect_gutenberg_border_width_values($gallery_attrs, $gallery_style) as $border_width_value) {
      $has_explicit_border_width = true;
      $modes[] = $this->classify_gutenberg_border_width($border_width_value);
    }

    if (!empty($gallery_block['innerBlocks']) && is_array($gallery_block['innerBlocks'])) {
      foreach ($gallery_block['innerBlocks'] as $inner_block) {
        if (empty($inner_block['blockName']) || $inner_block['blockName'] !== 'core/image') {
          continue;
        }

        $attrs = !empty($inner_block['attrs']) && is_array($inner_block['attrs'])
          ? $inner_block['attrs']
          : [];
        $style = !empty($attrs['style']) && is_array($attrs['style'])
          ? $attrs['style']
          : [];

        foreach ($this->collect_gutenberg_border_width_values($attrs, $style) as $border_width_value) {
          $has_explicit_border_width = true;
          $modes[] = $this->classify_gutenberg_border_width($border_width_value);
        }
      }
    }

    if (in_array('numeric', $modes, true)) {
      // Border width is explicit (0 or any numeric value) -> title below image.
      return 'below';
    }

    if (in_array('empty', $modes, true)) {
      // Empty border width in Gutenberg means title overlays on image.
      return 'bottom';
    }

    if ($has_explicit_border_width) {
      // If border width is explicitly set but non-numeric (for example variable/preset),
      // keep on-image title behavior.
      return 'bottom';
    }

    return 'below';
  }

  private function collect_gutenberg_border_width_values($attrs, $style) {
    $values = [];

    if (is_array($attrs) && array_key_exists('borderWidth', $attrs)) {
      $values[] = $attrs['borderWidth'];
    }

    if (is_array($style)) {
      if (isset($style['border']) && is_array($style['border']) && array_key_exists('width', $style['border'])) {
        $values[] = $style['border']['width'];
      }

      $image_border_width = $this->read_nested($style, ['elements', 'image', 'border', 'width']);
      if ($image_border_width !== null) {
        $values[] = $image_border_width;
      }
    }

    return $values;
  }

  private function classify_gutenberg_border_width($value) {
    if (is_array($value)) {
      $saw_empty = false;
      foreach ($value as $edge_value) {
        $mode = $this->classify_gutenberg_border_width($edge_value);
        if ($mode === 'numeric') {
          return 'numeric';
        }
        if ($mode === 'empty') {
          $saw_empty = true;
        }
      }

      return $saw_empty ? 'empty' : 'unknown';
    }

    if (is_numeric($value)) {
      return 'numeric';
    }

    if (!is_string($value)) {
      return 'unknown';
    }

    $normalized = trim($value);
    if ($normalized === '') {
      return 'empty';
    }

    if (preg_match('/[0-9]+(?:\.[0-9]+)?/', $normalized)) {
      return 'numeric';
    }

    return 'unknown';
  }

  private function cleanup_overrides($value) {
    if (!is_array($value)) {
      return $value;
    }

    foreach ($value as $key => $item) {
      if (is_array($item)) {
        $value[$key] = $this->cleanup_overrides($item);
        if ($value[$key] === []) {
          unset($value[$key]);
        }
      }
    }

    return $value;
  }
}
