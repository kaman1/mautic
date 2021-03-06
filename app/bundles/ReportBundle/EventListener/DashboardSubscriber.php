<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace Mautic\ReportBundle\EventListener;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\DashboardBundle\EventListener\DashboardSubscriber as MainDashboardSubscriber;
use Mautic\ReportBundle\Model\ReportModel;

/**
 * Class DashboardSubscriber
 */
class DashboardSubscriber extends MainDashboardSubscriber
{
    /**
     * @var ReportModel
     */
    protected $model;

    /**
     * @var CorePermissions
     */
    protected $security;

    /**
     * Define the name of the bundle/category of the widget(s)
     *
     * @var string
     */
    protected $bundle = 'report';

    /**
     * Define the widget(s)
     *
     * @var string
     */
    protected $types = array(
        'report' => array(
            'formAlias' => 'report_widget'
        ),
    );

    /**
     * Define permissions to see those widgets
     *
     * @var array
     */
    protected $permissions = array(
        'report:reports:viewown',
        'report:reports:viewother'
    );

    /**
     * DashboardSubscriber constructor.
     *
     * @param MauticFactory $factory
     * @param ReportModel   $model
     */
    public function __construct(MauticFactory $factory, ReportModel $model, CorePermissions $security)
    {
        $this->model    = $model;
        $this->security = $security;

        parent::__construct($factory);
    }

    /**
     * Set a widget detail when needed
     *
     * @param WidgetDetailEvent $event
     *
     * @return void
     */
    public function onWidgetDetailGenerate(WidgetDetailEvent $event)
    {
        $this->checkPermissions($event);

        if ($event->getType() == 'report') {
            $widget = $event->getWidget();
            $params = $widget->getParams();
            if (!$event->isCached()) {
                list($reportId, $graph) = explode(':', $params['graph']);
                $report = $this->model->getEntity($reportId);

                if ($report && $this->security->hasEntityAccess('report:reports:viewown', 'report:reports:viewother', $report->getCreatedBy())) {
                    $reportData = $this->model->getReportData(
                        $report,
                        null,
                        [
                            'ignoreTableData' => true,
                            'graphName'       => $graph,
                            'dateFrom'        => $params['dateFrom'],
                            'dateTo'          => $params['dateTo']
                        ]
                    );

                    if (isset($reportData['graphs'][$graph])) {
                        $graphData = $reportData['graphs'][$graph];
                        $event->setTemplateData(
                            [
                                'chartData'   => $graphData['data'],
                                'chartType'   => $graphData['type'],
                                'chartHeight' => $widget->getHeight() - 90,
                                'reportId'    => $report->getId(),
                                'dateFrom'    => $params['dateFrom'],
                                'dateTo'      => $params['dateTo']
                            ]
                        );
                    }
                }
            }

            $event->setTemplate('MauticReportBundle:SubscribedEvents\Dashboard:widget.html.php');
            $event->stopPropagation();
        }
    }
}
