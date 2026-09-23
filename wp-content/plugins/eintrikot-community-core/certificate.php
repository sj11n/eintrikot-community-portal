<?php
/**
 * Mitgliedsurkunde: Name, Mitgliedsnummer und Eintrittsdatum auf der hinterlegten Vorlage.
 *
 * Die Vorlage (mit Unterschriften) wird im Backend hochgeladen und nur in der Datenbank
 * gespeichert, nie im Repository oder als öffentliche Mediendatei.
 */
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}

/** Page size of the template in PDF points (A4) and where the texts sit, measured on the Canva original. */
const CERT_PAGE_W = 595.28;
const CERT_PAGE_H = 841.89;

function certificate_background() {
    $data = get_option('eintrikot_certificate_bg', '');
    return is_string($data) && $data !== '' ? base64_decode($data, true) : false;
}

function certificate_ready() {
    return function_exists('imagettftext') &&
        function_exists('imagecreatefromstring') &&
        certificate_background();
}

function certificate_font() {
    return __DIR__ . '/assets/montserrat-regular.ttf';
}

function member_number_label($number) {
    $number = preg_replace('/\D/', '', (string) $number);
    return $number === '' ? '' : str_pad($number, 4, '0', STR_PAD_LEFT);
}

/** Draws text with its left edge at x and its baseline at y (both in points). */
function certificate_text($image, $scale, $size, $x, $y, $color, $text) {
    imagettftext(
        $image,
        $size * $scale,
        0,
        (int) round($x * $scale),
        (int) round($y * $scale),
        $color,
        certificate_font(),
        $text
    );
}

/**
 * Certificate as JPEG bytes, or WP_Error.
 *
 * @param string $name   Full name
 * @param string $number Member number (digits)
 * @param string $date   Y-m-d
 */
function certificate_jpeg($name, $number, $date) {
    $bytes = certificate_background();
    if (!$bytes || !function_exists('imagettftext')) {
        return new \WP_Error(
            'certificate',
            'Für die Urkunde fehlt die Vorlage oder die Bildbearbeitung des Servers.'
        );
    }
    $image = @imagecreatefromstring($bytes);
    if (!$image) {
        return new \WP_Error('certificate', 'Die Urkunden-Vorlage kann nicht gelesen werden.');
    }
    imagealphablending($image, true);
    $scale = imagesx($image) / CERT_PAGE_W;
    $grey = imagecolorallocate($image, 0x4d, 0x4d, 0x4f);
    $dark = imagecolorallocate($image, 0x34, 0x34, 0x34);
    $warm = imagecolorallocate($image, 0x54, 0x48, 0x3f);

    // Name: shrinks for very long names so it never runs past the right margin.
    $size = 23.7;
    $box = imagettfbbox($size, 0, certificate_font(), $name);
    $width = $box[2] - $box[0];
    if ($width > 500) {
        $size *= 500 / $width;
    }
    certificate_text($image, $scale, $size, 48.9, 392.1, $grey, $name);
    $label = member_number_label($number);
    if ($label !== '') {
        certificate_text($image, $scale, 9.68, 49.9, 418.0, $dark, 'MITGLIEDSNUMMER: ' . $label);
    }
    $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);
    if ($d) {
        certificate_text($image, $scale, 9.3, 203.0, 651.2, $warm, $d->format('d.m.Y'));
    }
    ob_start();
    imagejpeg($image, null, 88);
    imagedestroy($image);
    return ob_get_clean();
}

/** Wraps a JPEG into a one-page A4 PDF. */
function jpeg_to_pdf($jpeg, $title) {
    $info = getimagesizefromstring($jpeg);
    if (!$info) {
        return new \WP_Error('certificate', 'Urkunde konnte nicht erstellt werden.');
    }
    $w = sprintf('%.2F', CERT_PAGE_W);
    $h = sprintf('%.2F', CERT_PAGE_H);
    $content = "q $w 0 0 $h 0 0 cm /Im0 Do Q";
    $title = str_replace(
        ['\\', '(', ')'],
        ['\\\\', '\\(', '\\)'],
        mb_convert_encoding($title, 'Windows-1252', 'UTF-8')
    );
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>",
        '<< /Type /XObject /Subtype /Image /Width ' .
        $info[0] .
        ' /Height ' .
        $info[1] .
        ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' .
        strlen($jpeg) .
        " >>\nstream\n" .
        $jpeg .
        "\nendstream",
        '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
        '<< /Title (' . $title . ') /Producer (EINTRIKOT Mitgliederportal) >>'
    ];
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($objects as $i => $body) {
        $offsets[] = strlen($pdf);
        $pdf .= $i + 1 . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .=
        'trailer << /Size ' .
        (count($objects) + 1) .
        " /Root 1 0 R /Info 6 0 R >>\nstartxref\n" .
        $xref .
        "\n%%EOF";
    return $pdf;
}

/** Certificate PDF for a portal member, or WP_Error. */
function member_certificate_pdf($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return new \WP_Error('certificate', 'Mitglied nicht gefunden.');
    }
    $number = (string) get_user_meta($user_id, 'eintrikot_member_number', true);
    $joined = (string) get_user_meta($user_id, 'eintrikot_joined', true);
    if ($number === '') {
        return new \WP_Error('certificate', 'Für dieses Mitglied ist keine Mitgliedsnummer hinterlegt.');
    }
    $jpeg = certificate_jpeg($user->display_name, $number, $joined ?: wp_date('Y-m-d'));
    return is_wp_error($jpeg)
        ? $jpeg
        : jpeg_to_pdf($jpeg, 'Mitgliedsurkunde ' . member_number_label($number) . ' ' . $user->display_name);
}

function certificate_filename($user_id) {
    $user = get_user_by('id', $user_id);
    return sanitize_file_name(
        'Mitgliedsurkunde-' .
            member_number_label(get_user_meta($user_id, 'eintrikot_member_number', true)) .
            '-' .
            remove_accents($user ? $user->display_name : 'Mitglied') .
            '.pdf'
    );
}

/** Download: the member's own certificate, or any certificate for the administration. */
add_action('template_redirect', function () {
    if (
        !(
            (int) get_option('eintrikot_portal_page') > 0 &&
            is_page((int) get_option('eintrikot_portal_page'))
        ) ||
        current_view() !== 'certificate'
    ) {
        return;
    }
    $id =
        isset($_GET['member']) && is_scalar($_GET['member'])
            ? absint($_GET['member'])
            : get_current_user_id();
    if (!member_access() || ($id !== get_current_user_id() && !manager_access())) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    $pdf = member_certificate_pdf($id);
    if (is_wp_error($pdf)) {
        wp_die(esc_html($pdf->get_error_message()), '', ['response' => 404]);
    }
    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . certificate_filename($id) . '"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Robots-Tag: noindex');
    echo $pdf;
    exit();
});
