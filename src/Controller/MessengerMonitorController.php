<?php

/*
 * This file is part of the zenstruck/messenger-monitor-bundle package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Monitor\Controller;

use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Lorisleiva\CronTranslator\CronTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;
use Zenstruck\Messenger\Monitor\History\Specification;
use Zenstruck\Messenger\Monitor\History\Storage;
use Zenstruck\Messenger\Monitor\ScheduleMonitor;
use Zenstruck\Messenger\Monitor\TransportMonitor;
use Zenstruck\Messenger\Monitor\WorkerMonitor;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class MessengerMonitorController extends AbstractController
{
    #[Route(name: 'zenstruck_messenger_monitor_dashboard')]
    public function dashboard(
        WorkerMonitor $workers,
        TransportMonitor $transports,
        ?Storage $storage = null,
        ?ScheduleMonitor $schedules = null,
        ?DateTimeFormatter $dateTimeFormatter = null,
    ): Response {
        if (!$storage) {
            throw new \LogicException('Storage must be configured to use the dashboard.');
        }

        return $this->render('@ZenstruckMessengerMonitor/dashboard.html.twig', [
            'workers' => $workers,
            'transports' => $transports,
            'snapshot' => Specification::new()->from(Specification::ONE_DAY_AGO)->snapshot($storage),
            'messages' => Specification::new()->snapshot($storage)->messages(),
            'schedules' => $schedules,
            'time_formatter' => $dateTimeFormatter,
            'duration_format' => $dateTimeFormatter && \method_exists($dateTimeFormatter, 'formatDuration'),
        ]);
    }

    #[Route('/history', name: 'zenstruck_messenger_monitor_history')]
    public function history(
        ?ScheduleMonitor $schedules = null,
        ?DateTimeFormatter $dateTimeFormatter = null,
    ): Response {
        return $this->render('@ZenstruckMessengerMonitor/history.html.twig', [
            'schedules' => $schedules,
            'time_formatter' => $dateTimeFormatter,
            'duration_format' => $dateTimeFormatter && \method_exists($dateTimeFormatter, 'formatDuration'),
        ]);
    }

    #[Route('/schedules/{name}', name: 'zenstruck_messenger_monitor_schedules', defaults: ['name' => null])]
    public function schedules(
        ?ScheduleMonitor $schedules = null,
        ?DateTimeFormatter $dateTimeFormatter = null,

        ?string $name = null,
    ): Response {
        if (!$schedules) {
            throw new \LogicException('Scheduler must be configured to use the dashboard.');
        }

        if (!\count($schedules)) {
            throw new \LogicException('No schedules configured.');
        }

        return $this->render('@ZenstruckMessengerMonitor/schedules.html.twig', [
            'schedules' => $schedules,
            'schedule' => $schedules->get($name),
            'time_formatter' => $dateTimeFormatter,
            'duration_format' => $dateTimeFormatter && \method_exists($dateTimeFormatter, 'formatDuration'),
            'cron_humanizer' => new class() {
                public function humanize(TriggerInterface $trigger, CronExpressionTrigger $cron, ?string $locale): string
                {
                    $title = 'Activate humanized version with composer require lorisleiva/cron-translator';
                    $body = (string) $cron;

                    if (\class_exists(CronTranslator::class)) {
                        $title = $body;
                        $body = CronTranslator::translate((string) $cron, $locale ?? 'en');
                    }

                    return \str_replace((string) $cron, \sprintf('<abbr title="%s">%s</abbr>', $title, $body), (string) $trigger);
                }
            },
        ]);
    }
}