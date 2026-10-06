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
 * recorder.js
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    let config = null;
    let recorder = null;
    let stream = null;
    let chunks = [];
    let blob = null;

    const region = selector =>
        document.querySelector(
            `[data-region="recorder"] ${selector}`
        );

    const setStatus = message => {
        const target = region(
            '[data-region="recorder-status"]'
        );

        if (target) {
            target.textContent = message;
        }
    };

    const stopStream = () => {
        if (stream) {
            stream.getTracks().forEach(
                track => track.stop()
            );

            stream = null;
        }
    };

    const preferredMime = () => {
        const types = [
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp8,opus',
            'video/webm',
            'video/mp4'
        ];

        return types.find(
            type =>
                window.MediaRecorder.isTypeSupported(
                    type
                )
        ) || '';
    };

    const start = async () => {
        const startButton = region(
            '[data-action="start-recording"]'
        );

        const stopButton = region(
            '[data-action="stop-recording"]'
        );

        const uploadButton = region(
            '[data-action="upload-recording"]'
        );

        const preview = region(
            '[data-region="recorder-preview"]'
        );

        try {
            stream =
                await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: true
                });

            preview.srcObject = stream;
            preview.controls = false;
            preview.muted = true;

            chunks = [];
            blob = null;

            const mimeType = preferredMime();

            recorder = mimeType
                ? new window.MediaRecorder(
                    stream,
                    {mimeType: mimeType}
                )
                : new window.MediaRecorder(
                    stream
                );

            recorder.ondataavailable = event => {
                if (
                    event.data &&
                    event.data.size > 0
                ) {
                    chunks.push(event.data);
                }
            };

            recorder.onstop = () => {
                blob = new Blob(
                    chunks,
                    {
                        type:
                            recorder.mimeType ||
                            'video/webm'
                    }
                );

                preview.srcObject = null;

                preview.src =
                    URL.createObjectURL(blob);

                preview.controls = true;
                preview.muted = false;

                uploadButton.disabled = false;

                setStatus(
                    preview.dataset.readyText ||
                    'Recording ready.'
                );

                stopStream();
            };

            recorder.start(1000);

            startButton.disabled = true;
            stopButton.disabled = false;
            uploadButton.disabled = true;

            setStatus(
                preview.dataset.recordingText ||
                'Recording…'
            );

        } catch (error) {
            stopStream();

            setStatus(
                preview.dataset.permissionErrorText ||
                'Camera or microphone access could not be started.'
            );
        }
    };

    const stop = () => {
        if (
            recorder &&
            recorder.state !== 'inactive'
        ) {
            recorder.stop();
        }

        const startButton = region(
            '[data-action="start-recording"]'
        );

        const stopButton = region(
            '[data-action="stop-recording"]'
        );

        startButton.disabled = false;
        stopButton.disabled = true;
    };

    const upload = async () => {
        const preview = region(
            '[data-region="recorder-preview"]'
        );

        const uploadButton = region(
            '[data-action="upload-recording"]'
        );

        if (!blob) {
            return;
        }

        if (
            config.maxbytes > 0 &&
            blob.size > config.maxbytes
        ) {
            setStatus(
                preview.dataset.tooLargeText ||
                'Recording is too large.'
            );

            return;
        }

        uploadButton.disabled = true;

        setStatus(
            preview.dataset.uploadingText ||
            'Uploading recording…'
        );

        const form = new FormData();

        form.append(
            'id',
            String(config.cmid)
        );

        form.append(
            'submissionid',
            String(config.submissionid)
        );

        form.append(
            'sesskey',
            M.cfg.sesskey
        );

        const extension =
            blob.type.includes('mp4')
                ? 'mp4'
                : 'webm';

        form.append(
            'recording',
            blob,
            `practice.${extension}`
        );

        try {
            const response = await fetch(
                config.uploadurl,
                {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin'
                }
            );

            const result =
                await response.json();

            if (
                !response.ok ||
                !result.success
            ) {
                throw new Error(
                    result.error ||
                    'upload-failed'
                );
            }

            setStatus(
                preview.dataset.savedText ||
                'Recording saved.'
            );

            window.setTimeout(
                () => window.location.reload(),
                500
            );

        } catch (error) {
            uploadButton.disabled = false;

            setStatus(
                preview.dataset.uploadFailedText ||
                'Recording could not be uploaded.'
            );
        }
    };

    const init = initialConfig => {
        config = initialConfig || {};

        const container =
            document.querySelector(
                '[data-region="recorder"]'
            );

        if (
            !container ||
            !navigator.mediaDevices ||
            !window.MediaRecorder
        ) {
            if (container) {
                const status = region(
                    '[data-region="recorder-status"]'
                );

                status.textContent =
                    container.dataset.unsupportedText ||
                    'Recording is not supported by this browser.';
            }

            return;
        }

        region(
            '[data-action="start-recording"]'
        ).addEventListener(
            'click',
            start
        );

        region(
            '[data-action="stop-recording"]'
        ).addEventListener(
            'click',
            stop
        );

        region(
            '[data-action="upload-recording"]'
        ).addEventListener(
            'click',
            upload
        );

        window.addEventListener(
            'pagehide',
            stopStream
        );
    };

    return {
        init: init
    };
});