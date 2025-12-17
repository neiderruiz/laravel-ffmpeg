<?php

namespace ProtoneMedia\LaravelFFMpeg\FFMpeg;

use FFMpeg\Format\Video\DefaultVideo;

class NVENCFormat extends DefaultVideo
{
    public function __construct($audioCodec = 'aac')
    {
        $this->videoCodec = 'h264_nvenc';
        $this->audioCodec = $audioCodec;
        $this->kiloBitrate = 6000;
        $this->audioKiloBitrate = 256;
    }

    public function supportBFrames(): bool
    {
        return false;
    }

    public function getAvailableVideoCodecs(): array
    {
        return ['h264_nvenc'];
    }

    public function getAvailableAudioCodecs(): array
    {
        return ['aac'];
    }

    public function getPasses(): int
    {
        return 1;
    }

    public function getExtraParams(): array
    {
        return [
            '-preset', 'p4',        // Balance velocidad/calidad NVENC
            '-tune', 'hq',          // High Quality
            '-crf', '23',           // Control de calidad
            '-pix_fmt', 'yuv420p'
        ];
    }
}