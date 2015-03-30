<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Settings\Controller;

use OC\App\DependencyAnalyzer;
use OC\App\Platform;
use OC\OCSClient;
use \OCP\AppFramework\Controller;
use OCP\ICacheFactory;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;

/**
 * @package OC\Settings\Controller
 */
class AppSettingsController extends Controller {

	/** @var \OCP\IL10N */
	private $l10n;
	/** @var IConfig */
	private $config;
	/** @var \OCP\ICache */
	private $cache;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param ICacheFactory $cache
	 */
	public function __construct($appName,
								IRequest $request,
								IL10N $l10n,
								IConfig $config,
								ICacheFactory $cache) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->config = $config;
		$this->cache = $cache->create($appName);
	}

	/**
	 * Get all available categories
	 * @return array
	 */
	public function listCategories() {

		if(!is_null($this->cache->get('listCategories'))) {
			return $this->cache->get('listCategories');
		}
		$categories = [
			['id' => 0, 'displayName' => (string)$this->l10n->t('Enabled')],
			['id' => 1, 'displayName' => (string)$this->l10n->t('Not enabled')],
		];

		$ocsClient = new OCSClient(
			\OC::$server->getHTTPClientService(),
			\OC::$server->getConfig(),
			\OC::$server->getLogger()
		);

		if($ocsClient->isAppStoreEnabled()) {
			// apps from external repo via OCS
			$ocs = $ocsClient->getCategories();
			if ($ocs) {
				foreach($ocs as $k => $v) {
					$categories[] = array(
						'id' => $k,
						'displayName' => str_replace('ownCloud ', '', $v)
					);
				}
			}
		}

		$this->cache->set('listCategories', $categories, 3600);

		return $categories;
	}

	/**
	 * Get all available apps in a category
	 *
	 * @param int $category
	 * @return array
	 */
	public function listApps($category = 0) {
		if(!is_null($this->cache->get('listApps-'.$category))) {
			$apps = $this->cache->get('listApps-'.$category);
		} else {
			switch ($category) {
				// installed apps
				case 0:
					$apps = $this->getInstalledApps();
					usort($apps, function ($a, $b) {
						$a = (string)$a['name'];
						$b = (string)$b['name'];
						if ($a === $b) {
							return 0;
						}
						return ($a < $b) ? -1 : 1;
					});
					break;
				// not-installed apps
				case 1:
					$apps = \OC_App::listAllApps(true);
					$apps = array_filter($apps, function ($app) {
						return !$app['active'];
					});
					usort($apps, function ($a, $b) {
						$a = (string)$a['name'];
						$b = (string)$b['name'];
						if ($a === $b) {
							return 0;
						}
						return ($a < $b) ? -1 : 1;
					});
					break;
				default:
					$apps = \OC_App::getAppstoreApps('approved', $category);
					if (!$apps) {
						$apps = array();
					} else {
						// don't list installed apps
						$installedApps = $this->getInstalledApps();
						$installedApps = array_map(function ($app) {
							if (isset($app['ocsid'])) {
								return $app['ocsid'];
							}
							return $app['id'];
						}, $installedApps);
						$apps = array_filter($apps, function ($app) use ($installedApps) {
							return !in_array($app['id'], $installedApps);
						});
					}

					// sort by score
					usort($apps, function ($a, $b) {
						$a = (int)$a['score'];
						$b = (int)$b['score'];
						if ($a === $b) {
							return 0;
						}
						return ($a > $b) ? -1 : 1;
					});
					break;
			}
		}

		// fix groups to be an array
		$dependencyAnalyzer = new DependencyAnalyzer(new Platform($this->config), $this->l10n);
		$apps = array_map(function($app) use ($dependencyAnalyzer) {

			// fix groups
			$groups = array();
			if (is_string($app['groups'])) {
				$groups = json_decode($app['groups']);
			}
			$app['groups'] = $groups;
			$app['canUnInstall'] = !$app['active'] && $app['removable'];

			// fix licence vs license
			if (isset($app['license']) && !isset($app['licence'])) {
				$app['licence'] = $app['license'];
			}

			// analyse dependencies
			$missing = $dependencyAnalyzer->analyze($app);
			$app['canInstall'] = empty($missing);
			$app['missingDependencies'] = $missing;

			return $app;
		}, $apps);

		$this->cache->set('listApps-'.$category, $apps, 300);

		return ['apps' => $apps, 'status' => 'success'];
	}

	/**
	 * @return array
	 */
	private function getInstalledApps() {
		$apps = \OC_App::listAllApps(true);
		$apps = array_filter($apps, function ($app) {
			return $app['active'];
		});
		return $apps;
	}
}
