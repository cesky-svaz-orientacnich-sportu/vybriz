<?php

namespace App\Helpers;

final class AssetsRevisioning
{
	public static function getAssetRevisionFunction()
	{
		return function(string $assetPath): string
		{
			$json = file_get_contents(__DIR__.'/../../www/assets/rev-manifest.json');
			if (!$json) return AssetsRevisioning::warnAboutAssetError($assetPath);

			$revisions = json_decode($json ?? []);
			$revisionPath = "/assets/$assetPath";

			if (!property_exists($revisions, $revisionPath)) return AssetsRevisioning::warnAboutAssetError($assetPath);

			return $revisions->$revisionPath;
		};
	}

	public static function warnAboutAssetError(string $assetPath): string
	{
		trigger_error("Asset '$assetPath' was not found in the revision manifest.", E_USER_WARNING);

		return '';
	}
}
