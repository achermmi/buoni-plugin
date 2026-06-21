<?php
/**
 * Plugin Name: Buoni Botega da la Lavizzara
 * Description: Gestione buoni per Botega da la Lavizzara.
 * Version: 1.0.0
 * Author: Botega da la Lavizzara
 * Text Domain: buoni-plugin
 */

if (! defined('ABSPATH')) {
    exit;
}

final class BDLV_Buoni_Plugin
{
    private const POST_TYPE = 'bdlv_buono';
    private const DEFAULT_SHORTCODE_LIMIT = 50;

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_buono_meta']);
        add_shortcode('bdlv_buoni', [$this, 'render_shortcode']);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Buoni', 'buoni-plugin'),
                'singular_name' => __('Buono', 'buoni-plugin'),
                'add_new_item' => __('Aggiungi nuovo buono', 'buoni-plugin'),
                'edit_item' => __('Modifica buono', 'buoni-plugin'),
                'new_item' => __('Nuovo buono', 'buoni-plugin'),
                'view_item' => __('Visualizza buono', 'buoni-plugin'),
                'search_items' => __('Cerca buoni', 'buoni-plugin'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-tickets-alt',
            'capability_type' => 'post',
            'has_archive' => false,
            'rewrite' => false,
        ]);
    }

    public function register_meta_boxes(): void
    {
        add_meta_box(
            'bdlv_buono_dettagli',
            __('Dettagli buono', 'buoni-plugin'),
            [$this, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('bdlv_save_buono', 'bdlv_buono_nonce');

        $code = (string) get_post_meta($post->ID, '_bdlv_code', true);
        $value_meta = get_post_meta($post->ID, '_bdlv_value', true);
        $value = is_numeric($value_meta) ? (float) $value_meta : 0.0;
        $recipient = (string) get_post_meta($post->ID, '_bdlv_recipient', true);
        $redeemed = (string) get_post_meta($post->ID, '_bdlv_redeemed', true);
        ?>
        <p>
            <label for="bdlv_code"><strong><?php esc_html_e('Codice', 'buoni-plugin'); ?></strong></label><br>
            <input type="text" id="bdlv_code" name="bdlv_code" value="<?php echo esc_attr($code); ?>" class="widefat" />
        </p>
        <p>
            <label for="bdlv_value"><strong><?php esc_html_e('Valore (€)', 'buoni-plugin'); ?></strong></label><br>
            <input type="number" id="bdlv_value" name="bdlv_value" value="<?php echo esc_attr((string) $value); ?>" class="small-text" min="0" step="0.01" />
        </p>
        <p>
            <label for="bdlv_recipient"><strong><?php esc_html_e('Destinatario', 'buoni-plugin'); ?></strong></label><br>
            <input type="text" id="bdlv_recipient" name="bdlv_recipient" value="<?php echo esc_attr($recipient); ?>" class="widefat" />
        </p>
        <p>
            <label>
                <input type="checkbox" name="bdlv_redeemed" value="1" <?php checked($redeemed, '1'); ?> />
                <?php esc_html_e('Buono riscattato', 'buoni-plugin'); ?>
            </label>
        </p>
        <?php
    }

    public function save_buono_meta(int $post_id): void
    {
        if (! isset($_POST['bdlv_buono_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bdlv_buono_nonce'])), 'bdlv_save_buono')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $code = isset($_POST['bdlv_code']) ? sanitize_text_field(wp_unslash($_POST['bdlv_code'])) : '';
        $recipient = isset($_POST['bdlv_recipient']) ? sanitize_text_field(wp_unslash($_POST['bdlv_recipient'])) : '';
        $value_raw = isset($_POST['bdlv_value']) ? sanitize_text_field(wp_unslash($_POST['bdlv_value'])) : '0';
        $value = is_numeric($value_raw) ? (float) $value_raw : 0.0;

        if ($value < 0) {
            $value = 0.0;
        }
        $redeemed = isset($_POST['bdlv_redeemed']) ? '1' : '0';

        update_post_meta($post_id, '_bdlv_code', $code);
        update_post_meta($post_id, '_bdlv_recipient', $recipient);
        update_post_meta($post_id, '_bdlv_value', (string) round($value, 2));
        update_post_meta($post_id, '_bdlv_redeemed', $redeemed);
    }

    public function render_shortcode(array $atts = []): string
    {
        $atts = shortcode_atts(['limit' => self::DEFAULT_SHORTCODE_LIMIT], $atts, 'bdlv_buoni');
        $limit = max(1, min((int) $atts['limit'], 100));

        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_bdlv_redeemed',
                    'value' => '0',
                    'compare' => '=',
                ],
                [
                    'key' => '_bdlv_redeemed',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        if (! $query->have_posts()) {
            return '<p>' . esc_html__('Nessun buono disponibile.', 'buoni-plugin') . '</p>';
        }

        ob_start();
        echo '<ul class="bdlv-buoni-list">';

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $code = (string) get_post_meta($post_id, '_bdlv_code', true);
            $value = (float) get_post_meta($post_id, '_bdlv_value', true);
            $recipient = (string) get_post_meta($post_id, '_bdlv_recipient', true);

            echo '<li>';
            echo '<strong>' . esc_html(get_the_title()) . '</strong>';

            if ($code !== '') {
                echo ' - ' . esc_html__('Codice:', 'buoni-plugin') . ' ' . esc_html($code);
            }

            if ($value > 0) {
                $formatted_value = number_format_i18n($value, 2);
                echo ' - ' . sprintf(
                    esc_html__('Valore: %1$s %2$s', 'buoni-plugin'),
                    esc_html($formatted_value),
                    esc_html('€')
                );
            }

            if ($recipient !== '') {
                echo ' - ' . esc_html__('Destinatario:', 'buoni-plugin') . ' ' . esc_html($recipient);
            }

            echo '</li>';
        }

        echo '</ul>';
        wp_reset_postdata();

        return (string) ob_get_clean();
    }
}

function bdlv_buoni_plugin(): BDLV_Buoni_Plugin
{
    static $instance = null;

    if ($instance === null) {
        $instance = new BDLV_Buoni_Plugin();
    }

    return $instance;
}

bdlv_buoni_plugin();
