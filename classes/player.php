<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Reference video source and player helper.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videopractice;

use context_module;
use moodle_url;
use stdClass;

/**
 * Builds and validates reference video sources.
 */
class player {
    /**
     * Returns supported source options.
     *
     * @return array
     */
    public static function source_options(): array {
        return [
            'upload' => get_string('sourceupload', 'videopractice'),
            'url' => get_string('sourceurl', 'videopractice'),
            'youtube' => get_string('sourceyoutube', 'videopractice'),
            'vimeo' => get_string('sourcevimeo', 'videopractice'),
        ];
    }

    /**
     * Validates source-specific form data.
     *
     * @param array $data Form data.
     * @return string Empty string when valid.
     */
    public static function validate_source(array $data): string {
        $source = $data['referencesource'] ?? '';
        if (!array_key_exists($source, self::source_options())) {
            return get_string('invalidsource', 'videopractice');
        }
        if ($source === 'upload') {
            $draftid = (int)($data['referencevideo'] ?? 0);
            $info = $draftid ? file_get_draft_area_info($draftid) : ['filecount' => 0];
            return empty($info['filecount']) ? get_string('referencevideorequired', 'videopractice') : '';
        }

        $url = trim((string)($data['referenceurl'] ?? ''));
        if ($source === 'url') {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return get_string('invalidvideourl', 'videopractice');
            }
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            $extension = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (!in_array($scheme, ['http', 'https'], true) ||
                !in_array($extension, ['mp4', 'webm', 'ogv', 'm4v', 'mov', 'm3u8'], true)) {
                return get_string('invaliddirecturl', 'videopractice');
            }
            return '';
        }
        if ($source === 'youtube') {
            return self::youtube_id($url) === '' ? get_string('invalidyoutubeurl', 'videopractice') : '';
        }
        if ($source === 'vimeo') {
            return self::vimeo_config($url) === [] ? get_string('invalidvimeourl', 'videopractice') : '';
        }
        return '';
    }

    /**
     * Prepares uploaded reference video for editing.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Module context.
     * @return void
     */
    public static function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $draftid = file_get_submitted_draft_itemid('referencevideo');
        file_prepare_draft_area($draftid, $context->id, 'mod_videopractice', 'referencevideo', 0, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ]);
        $defaultvalues['referencevideo'] = $draftid;
    }

    /**
     * Saves or removes the protected reference video.
     *
     * @param stdClass $data Activity form data.
     * @param context_module $context Module context.
     * @return void
     */
    public static function save_reference_file(stdClass $data, context_module $context): void {
        $fs = get_file_storage();
        if ($data->referencesource !== 'upload') {
            $fs->delete_area_files($context->id, 'mod_videopractice', 'referencevideo', 0);
            return;
        }
        if (!isset($data->referencevideo)) {
            return;
        }
        file_save_draft_area_files($data->referencevideo, $context->id, 'mod_videopractice', 'referencevideo', 0, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ]);
    }

    /**
     * Builds template and client configuration for the reference player.
     *
     * @param stdClass $activity Activity record.
     * @param context_module $context Module context.
     * @return array
     */
    public static function build(stdClass $activity, context_module $context): array {
        $config = [
            'source' => $activity->referencesource,
            'html5' => false,
            'youtube' => false,
            'vimeo' => false,
            'url' => '',
            'youtubeid' => '',
            'vimeoid' => '',
            'vimeohash' => '',
        ];
        if ($activity->referencesource === 'upload') {
            $config['html5'] = true;
            $config['url'] = self::reference_file_url($context);
        } else if ($activity->referencesource === 'url') {
            $config['html5'] = true;
            $config['url'] = (string)$activity->referenceurl;
        } else if ($activity->referencesource === 'youtube') {
            $config['youtube'] = true;
            $config['youtubeid'] = self::youtube_id((string)$activity->referenceurl);
            $config['youtubeurl'] = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($config['youtubeid']) .
                '?enablejsapi=1&rel=0';
        } else if ($activity->referencesource === 'vimeo') {
            $vimeo = self::vimeo_config((string)$activity->referenceurl);
            $config['vimeo'] = true;
            $config['vimeoid'] = $vimeo['id'] ?? '';
            $config['vimeohash'] = $vimeo['hash'] ?? '';
            $config['vimeourl'] = 'https://player.vimeo.com/video/' . rawurlencode($config['vimeoid']);
            if ($config['vimeohash'] !== '') {
                $config['vimeourl'] .= '?h=' . rawurlencode($config['vimeohash']);
            }
        }
        return $config;
    }

    /**
     * Gets the first reference file URL.
     *
     * @param context_module $context Module context.
     * @return string
     */
    private static function reference_file_url(context_module $context): string {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_videopractice',
            'referencevideo',
            0,
            'filename',
            false
        );
        if (!$files) {
            return '';
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $context->id,
            'mod_videopractice',
            'referencevideo',
            0,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * Extracts a YouTube identifier from supported URL forms.
     *
     * @param string $url URL.
     * @return string
     */
    private static function youtube_id(string $url): string {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        $id = '';
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $parts = explode('/', $path);
            $id = (string)($parts[0] ?? '');
        } else if (in_array($host, [
            'youtube.com', 'www.youtube.com', 'm.youtube.com',
            'youtube-nocookie.com', 'www.youtube-nocookie.com',
        ], true)) {
            parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
            $id = (string)($query['v'] ?? '');
            if ($id === '' && preg_match('~(?:embed|shorts)/([A-Za-z0-9_-]{6,20})~', $path, $matches)) {
                $id = $matches[1];
            }
        }
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? $id : '';
    }

    /**
     * Extracts Vimeo id and optional unlisted hash.
     *
     * @param string $url Vimeo URL.
     * @return array
     */
    private static function vimeo_config(string $url): array {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [];
        }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return [];
        }
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        if (!preg_match('~^(?:video/)?(\d+)(?:/([A-Za-z0-9]+))?$~', $path, $matches)) {
            return [];
        }
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $hash = (string)($matches[2] ?? '');
        if ($hash === '' && !empty($query['h']) && preg_match('/^[A-Za-z0-9]+$/', $query['h'])) {
            $hash = (string)$query['h'];
        }
        return ['id' => $matches[1], 'hash' => $hash];
    }
}
