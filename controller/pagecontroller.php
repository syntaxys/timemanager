<?php

namespace OCA\TimeManager\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCA\TimeManager\Db\Client;
use OCA\TimeManager\Db\ClientMapper;
use OCA\TimeManager\Db\ProjectMapper;
use OCA\TimeManager\Db\TaskMapper;
use OCA\TimeManager\Db\TimeMapper;
use OCA\TimeManager\Db\CommitMapper;
use OCA\TimeManager\Db\storageHelper;
use OCA\TimeManager\Helper\Cleaner;
use OCA\TimeManager\Helper\UUID;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

class PageController extends Controller {


	/** @var ClientMapper mapper for client entity */
	protected $clientMapper;
	/** @var ProjectMapper mapper for project entity */
	protected $projectMapper;
	/** @var TaskMapper mapper for task entity */
	protected $taskMapper;
	/** @var TimeMapper mapper for time entity */
	protected $timeMapper;
	/** @var ClientMapper mapper for client entity */
	protected $commitMapper;
	/** @var StorageHelper helper for working on the stored data */
	protected $storageHelper;
	/** @var string user ID */
	protected $userId;

	/**
	 * constructor of the controller
	 * @param string $appName the name of the app
	 * @param IRequest $request an instance of the request
	 * @param ClientMapper $clientMapper mapper for client entity
	 * @param ProjectMapper $projectMapper mapper for project entity
	 * @param TaskMapper $taskMapper mapper for task entity
	 * @param TimeMapper $timeMapper mapper for time entity
	 * @param string $userId user id
	 */
	function __construct($appName,
								IRequest $request,
								ClientMapper $clientMapper,
								ProjectMapper $projectMapper,
								TaskMapper $taskMapper,
								TimeMapper $timeMapper,
								CommitMapper $commitMapper,
								$userId
								) {
		parent::__construct($appName, $request);
		$this->clientMapper = $clientMapper;
		$this->projectMapper = $projectMapper;
		$this->taskMapper = $taskMapper;
		$this->timeMapper = $timeMapper;
		$this->commitMapper = $commitMapper;
		$this->userId = $userId;
		$this->storageHelper = new StorageHelper(
			$this->clientMapper,
			$this->projectMapper,
			$this->taskMapper,
			$this->timeMapper,
			$this->commitMapper,
			$userId
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	function index() {
		return new TemplateResponse('timemanager', 'index');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	function clients() {
		$clients = $this->clientMapper->findActiveForCurrentUser('name');

		// Enhance clients with additional information.
		if(count($clients) > 0) {
			foreach($clients as $index => $client) {
				// Number of projects
				$clients[$index]->project_count = $this->clientMapper->countProjects($client->getUuid());
				// Sum up client times
				$clients[$index]->hours = $this->clientMapper->getHours($client->getUuid());
			}
		}

		return new TemplateResponse('timemanager', 'clients', array(
			'clients' => $clients,
			'requesttoken' => (\OC::$server->getSession()) ? \OCP\Util::callRegister() : ''
		));
	}

	/**
	 * @NoAdminRequired
	 */
	function addClient($name='Unnamed', $note='') {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		$this->storageHelper->addOrUpdateObject(array(
			'name' => $name,
			'note' => $note,
			'commit' => $commit
		), 'clients');
		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.clients'));
	}

	/**
	 * @NoAdminRequired
	 */
	function deleteClient($uuid) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		// Get client
		$client = $this->clientMapper->getObjectById($uuid);
		// Delete object
		$client->setChanged(date('Y-m-d H:i:s'));
		$client->setCommit($commit);
		$client->setStatus('deleted');
		$this->clientMapper->update($client);

		// Delete children
		$this->clientMapper->deleteChildrenForEntityById($uuid, $commit);

		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.clients'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	function projects($client=null) {
		$clients = $this->clientMapper->findActiveForCurrentUser();
		if($client) {
			$projects = $this->projectMapper->getActiveObjectsByAttributeValue('client_uuid', $client);
			$client_data = $this->clientMapper->getActiveObjectsByAttributeValue('uuid', $client, 'name');
			// Sum up client times
			if(count($client_data) === 1) {
				$client_data[0]->hours = $this->clientMapper->getHours($client_data[0]->getUuid());
			}
		} else {
			$projects = $this->projectMapper->findActiveForCurrentUser();
		}

		// Enhance projects with additional information.
		if(count($projects) > 0) {
			foreach($projects as $index => $project) {
				// Count tasks
				$projects[$index]->task_count = $this->projectMapper->countTasks($project->getUuid());
				// Sum up project times
				$projects[$index]->hours = $this->projectMapper->getHours($project->getUuid());
			}
		}

		return new TemplateResponse('timemanager', 'projects', array('projects' => $projects, 'client' => (($client_data && count($client_data) > 0) ? $client_data[0] : null), 'clients' => $clients));
	}

	/**
	 * @NoAdminRequired
	 */
	function addProject($name, $client) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		$this->storageHelper->addOrUpdateObject(array(
			'name' => $name,
			'client_uuid' => $client,
			'commit' => $commit
		), 'projects');
		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.projects') . '?client=' . $client);
	}

	/**
	 * @NoAdminRequired
	 */
	function deleteProject($uuid, $client) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		// Get client
		$project = $this->projectMapper->getObjectById($uuid);
		// Delete object
		$project->setChanged(date('Y-m-d H:i:s'));
		$project->setCommit($commit);
		$project->setStatus('deleted');
		$this->projectMapper->update($project);

		// Delete children
		$this->projectMapper->deleteChildrenForEntityById($uuid, $commit);

		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.projects') . '?client=' . $client);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	function tasks($project) {
		$clients = $this->clientMapper->findActiveForCurrentUser();
		$projects = $this->projectMapper->findActiveForCurrentUser();
		if($project) {
			$tasks = $this->taskMapper->getActiveObjectsByAttributeValue('project_uuid', $project);
			$project_data = $this->projectMapper->getActiveObjectsByAttributeValue('uuid', $project);
			$client_data = $this->clientMapper->getActiveObjectsByAttributeValue('uuid', $project_data[0]->getClientUuid(), 'name');
			// Sum up project times
			if(count($project_data) === 1) {
				$project_data[0]->hours = $this->projectMapper->getHours($project_data[0]->getUuid());
			}
			// Sum up client times
			if(count($client_data) === 1) {
				$client_data[0]->hours = $this->clientMapper->getHours($client_data[0]->getUuid());
			}
		} else {
			$tasks = $this->taskMapper->findActiveForCurrentUser();
		}

		// Enhance tasks with additional information.
		if(count($tasks) > 0) {
			foreach($tasks as $index => $task) {
				// Sum up project times
				$tasks[$index]->hours = $this->taskMapper->getHours($task->getUuid());
			}
		}

		return new TemplateResponse('timemanager', 'tasks', array(
			'tasks' => $tasks,
			'project' => (($project_data && count($project_data) > 0) ? $project_data[0] : null),
			'client' => (($client_data && count($client_data) > 0) ? $client_data[0] : null),
			'projects' => $projects,
			'clients' => $clients
		));
	}

	/**
	 * @NoAdminRequired
	 */
	function addTask($name, $project) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		$this->storageHelper->addOrUpdateObject(array(
			'name' => $name,
			'project_uuid' => $project,
			'commit' => $commit
		), 'tasks');
		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.tasks') . '?project=' . $project);
	}

	/**
	 * @NoAdminRequired
	 */
	function deleteTask($uuid, $project) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		// Get client
		$task = $this->taskMapper->getObjectById($uuid);
		// Delete object
		$task->setChanged(date('Y-m-d H:i:s'));
		$task->setCommit($commit);
		$task->setStatus('deleted');
		$this->taskMapper->update($task);

		// Delete children
		$this->taskMapper->deleteChildrenForEntityById($uuid, $commit);

		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.tasks') . '?project=' . $project);
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	function times($task) {
		$clients = $this->clientMapper->findActiveForCurrentUser();
		$projects = $this->projectMapper->findActiveForCurrentUser();
		$tasks = $this->taskMapper->findActiveForCurrentUser();
		if($task) {
			$times = $this->timeMapper->getActiveObjectsByAttributeValue('task_uuid', $task, 'start');
			$task_data = $this->taskMapper->getActiveObjectsByAttributeValue('uuid', $task);
			$project_data = $this->projectMapper->getActiveObjectsByAttributeValue('uuid', $task_data[0]->getProjectUuid());
			$client_data = $this->clientMapper->getActiveObjectsByAttributeValue('uuid', $project_data[0]->getClientUuid(), 'name');
			// Sum up task times
			if(count($task_data) === 1) {
				$task_data[0]->hours = $this->taskMapper->getHours($task_data[0]->getUuid());
			}
			// Sum up project times
			if(count($project_data) === 1) {
				$project_data[0]->hours = $this->projectMapper->getHours($project_data[0]->getUuid());
			}
			// Sum up client times
			if(count($client_data) === 1) {
				$client_data[0]->hours = $this->clientMapper->getHours($client_data[0]->getUuid());
			}
		} else {
			$times = $this->timeMapper->findActiveForCurrentUser();
		}

		return new TemplateResponse('timemanager', 'times', array(
			'times' => $times,
			'task' => (($task_data && count($task_data) > 0) ? $task_data[0] : null),
			'project' => (($project_data && count($project_data) > 0) ? $project_data[0] : null),
			'client' => (($client_data && count($client_data) > 0) ? $client_data[0] : null),
			'tasks' => $tasks,
			'projects' => $projects,
			'clients' => $clients
		));
	}

	/**
	 * @NoAdminRequired
	 */
	function addTime($duration, $date, $note, $task) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		// Convert 1,25 to 1.25
		$duration = str_replace(',', '.', $duration);
		// Cast to float
		$duration = (float)$duration;
		// Calculate start and end from duration
		if(!empty($date)) {
			// Add 24 hours to make it end of the day.
			$end = date('Y-m-d H:i:s', strtotime($date) + 60 * 60 * 24);
		} else {
			$end = date('Y-m-d H:i:s');
		}
		$start = date('Y-m-d H:i:s', strtotime($end) - 60 * 60 * $duration);
		$this->storageHelper->addOrUpdateObject(array(
			'name' => $name,
			'start' => $start, // now - duration
			'end' => $end, // now
			'task_uuid' => $task,
			'commit' => $commit,
			'note' => $note
		), 'times');
		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.times') . '?task=' . $task);
	}

	/**
	 * @NoAdminRequired
	 */
	function deleteTime($uuid, $task) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		// Get client
		$time = $this->timeMapper->getObjectById($uuid);
		// Delete object
		$time->setChanged(date('Y-m-d H:i:s'));
		$time->setCommit($commit);
		$time->setStatus('deleted');
		$this->timeMapper->update($time);

		// Delete children
		$this->timeMapper->deleteChildrenForEntityById($uuid, $commit);

		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.times') . '?task=' . $task);
	}

	/**
	 * @NoAdminRequired
	 */
	function payTime($uuid, $task) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		// Get client
		$time = $this->timeMapper->getObjectById($uuid);
		// Adjust payment status object
		$time->setChanged(date('Y-m-d H:i:s'));
		$time->setCommit($commit);
		$time->setPaymentStatus('paid');
		$this->timeMapper->update($time);

		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.times') . '?task=' . $task);
	}

	/**
	 * @NoAdminRequired
	 */
	function unpayTime($uuid, $task) {
		$commit = UUID::v4();
		$this->storageHelper->insertCommit($commit);
		// Get client
		$time = $this->timeMapper->getObjectById($uuid);
		// Adjust payment status
		$time->setChanged(date('Y-m-d H:i:s'));
		$time->setCommit($commit);
		$time->setPaymentStatus('');
		$this->timeMapper->update($time);

		$urlGenerator = \OC::$server->getURLGenerator();
		return new RedirectResponse($urlGenerator->linkToRoute('timemanager.page.times') . '?task=' . $task);
	}
}