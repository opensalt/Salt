<?php

namespace App\Serializer\Api1;

use App\DTO\Api1\ImsxStatusInfo;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ImsxStatusInfoNormalizer implements NormalizerInterface
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ImsxStatusInfo;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ImsxStatusInfo::class => true];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof ImsxStatusInfo) {
            throw new \InvalidArgumentException('ImsxStatusInfo object expected');
        }

        $return = [
            'imsx_codeMajor' => $data->codeMajor,
            'imsx_severity' => $data->severity,
            'imsx_description' => $data->description,
        ];

        if (!empty($data->codeMinor)) {
            foreach ($data->codeMinor->codeMinorField as $minor) {
                $return['imsx_codeMinor']['imsx_codeMinorField'][] = [
                    'ims_codeMinorFieldName' => $minor->name,
                    'ims_codeMinorFieldValue' => $minor->value,
                ];
            }
        }

        return $return;
    }
}
