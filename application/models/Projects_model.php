<?php

use app\services\projects\Gantt;
use app\services\projects\AllProjectsGantt;
use app\services\projects\HoursOverviewChart;

defined('BASEPATH') or exit('No direct script access allowed');

class Projects_model extends App_Model
{
    private $project_settings;

    public function __construct()
    {
        parent::__construct();

        $project_settings = [
            'available_features',
            'view_tasks',
            'create_tasks',
            'edit_tasks',
            'comment_on_tasks',
            'view_task_comments',
            'view_task_attachments',
            'view_task_checklist_items',
            'upload_on_tasks',
            'view_task_total_logged_time',
            'view_finance_overview',
            'upload_files',
            'open_discussions',
            'view_milestones',
            'view_gantt',
            'view_timesheets',
            'view_activity_log',
            'view_team_members',
            'hide_tasks_on_main_tasks_table',
        ];

        $this->project_settings = hooks()->apply_filters('project_settings', $project_settings);
    }

    public function get_project_statuses()
    {
        $statuses = hooks()->apply_filters('before_get_project_statuses', [
            [
                'id' => 1,
                'color' => '#475569',
                'name' => _l('project_status_1'),
                'order' => 1,
                'filter_default' => true,
            ],
            [
                'id' => 2,
                'color' => '#2563eb',
                'name' => _l('project_status_2'),
                'order' => 2,
                'filter_default' => true,
            ],
            [
                'id' => 3,
                'color' => '#f97316',
                'name' => _l('project_status_3'),
                'order' => 3,
                'filter_default' => true,
            ],
            [
                'id' => 4,
                'color' => '#16a34a',
                'name' => _l('project_status_4'),
                'order' => 100,
                'filter_default' => false,
            ],
            [
                'id' => 5,
                'color' => '#94a3b8',
                'name' => _l('project_status_5'),
                'order' => 4,
                'filter_default' => false,
            ],
        ]);

        usort($statuses, function ($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $statuses;
    }

    public function get_distinct_tasks_timesheets_staff($project_id)
    {
        return $this->db->query('SELECT DISTINCT staff_id FROM ' . db_prefix() . 'taskstimers LEFT JOIN ' . db_prefix() . 'tasks ON ' . db_prefix() . 'tasks.id = ' . db_prefix() . 'taskstimers.task_id WHERE rel_type="project" AND rel_id=' . $this->db->escape_str($project_id))->result_array();
    }

    public function get_distinct_projects_members()
    {
        return $this->db->query('SELECT staff_id, firstname, lastname FROM ' . db_prefix() . 'project_members JOIN ' . db_prefix() . 'staff ON ' . db_prefix() . 'staff.staffid=' . db_prefix() . 'project_members.staff_id GROUP by staff_id order by firstname ASC')->result_array();
    }

    public function get_most_used_billing_type()
    {
        return $this->db->query('SELECT billing_type, COUNT(*) AS total_usage
                FROM ' . db_prefix() . 'projects
                GROUP BY billing_type
                ORDER BY total_usage DESC
                LIMIT 1')->row();
    }

    public function timers_started_for_project($project_id, $where = [], $task_timers_where = [])
    {
        $this->db->where($where);
        $this->db->where('end_time IS NULL');
        $this->db->where(db_prefix() . 'tasks.rel_id', $project_id);
        $this->db->where(db_prefix() . 'tasks.rel_type', 'project');
        $this->db->join(db_prefix() . 'tasks', db_prefix() . 'tasks.id=' . db_prefix() . 'taskstimers.task_id');
        $total = $this->db->count_all_results(db_prefix() . 'taskstimers');

        return $total > 0 ? true : false;
    }

    public function pin_action($id)
    {
        if (total_rows(db_prefix() . 'pinned_projects', [
            'staff_id' => get_staff_user_id(),
            'project_id' => $id,
        ]) == 0) {
            $this->db->insert(db_prefix() . 'pinned_projects', [
                'staff_id' => get_staff_user_id(),
                'project_id' => $id,
            ]);

            return true;
        }
        $this->db->where('project_id', $id);
        $this->db->where('staff_id', get_staff_user_id());
        $this->db->delete(db_prefix() . 'pinned_projects');

        return true;
    }

    public function get_currency($id)
    {
        $this->load->model('currencies_model');
        $customer_currency = $this->clients_model->get_customer_default_currency(get_client_id_by_project_id($id));
        if ($customer_currency != 0) {
            $currency = $this->currencies_model->get($customer_currency);
        } else {
            $currency = $this->currencies_model->get_base_currency();
        }

        return $currency;
    }

    public function calc_progress($id)
    {
        $this->db->select('progress_from_tasks,progress,status');
        $this->db->where('id', $id);
        $project = $this->db->get(db_prefix() . 'projects')->row();

        if ($project->status == 4) {
            return 100;
        }

        if ($project->progress_from_tasks == 1) {
            return $this->calc_progress_by_tasks($id);
        }

        return $project->progress;
    }

    public function calc_progress_by_tasks($id)
    {
        $total_project_tasks = total_rows(db_prefix() . 'tasks', [
            'rel_type' => 'project',
            'rel_id' => $id,
        ]);
        $total_finished_tasks = total_rows(db_prefix() . 'tasks', [
            'rel_type' => 'project',
            'rel_id' => $id,
            'status' => 5,
        ]);

        $percent = 0;
        if ($total_finished_tasks >= floatval($total_project_tasks)) {
            $percent = 100;
        } else {
            if ($total_project_tasks !== 0) {
                $percent = number_format(($total_finished_tasks * 100) / $total_project_tasks, 2);
            }
        }

        return $percent;
    }

    public function get_last_project_settings()
    {
        $this->db->select('id');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $last_project = $this->db->get(db_prefix() . 'projects')->row();
        if ($last_project) {
            return $this->get_project_settings($last_project->id);
        }

        return [];
    }

    public function get_settings()
    {
        return $this->project_settings;
    }

    public function get($id = '', $where = [])
    {
        $this->db->where($where);
        if (is_numeric($id)) {
            $this->db->where('id', $id);
            $project = $this->db->get(db_prefix() . 'projects')->row();
            if ($project) {
                $project->shared_vault_entries = $this->clients_model->get_vault_entries($project->clientid, ['share_in_projects' => 1]);
                $settings = $this->get_project_settings($id);

                // SYNC NEW TABS
                $tabs = get_project_tabs_admin();
                $tabs_flatten = [];
                $settings_available_features = [];

                $available_features_index = false;
                foreach ($settings as $key => $setting) {
                    if ($setting['name'] == 'available_features') {
                        $available_features_index = $key;
                        $available_features = unserialize($setting['value']);
                        if (is_array($available_features)) {
                            foreach ($available_features as $name => $avf) {
                                $settings_available_features[] = $name;
                            }
                        }
                    }
                }
                foreach ($tabs as $tab) {
                    if (isset($tab['collapse'])) {
                        foreach ($tab['children'] as $d) {
                            $tabs_flatten[] = $d['slug'];
                        }
                    } else {
                        $tabs_flatten[] = $tab['slug'];
                    }
                }
                if (count($settings_available_features) != $tabs_flatten) {
                    foreach ($tabs_flatten as $tab) {
                        if (!in_array($tab, $settings_available_features)) {
                            if ($available_features_index) {
                                $current_available_features_settings = $settings[$available_features_index];
                                $tmp = unserialize($current_available_features_settings['value']);
                                $tmp[$tab] = 1;
                                $this->db->where('id', $current_available_features_settings['id']);
                                $this->db->update(db_prefix() . 'project_settings', ['value' => serialize($tmp)]);
                            }
                        }
                    }
                }

                $project->settings = new StdClass();

                foreach ($settings as $setting) {
                    $project->settings->{$setting['name']} = $setting['value'];
                }

                $project->client_data = new StdClass();
                $project->client_data = $this->clients_model->get($project->clientid);

                $project = hooks()->apply_filters('project_get', $project);
                $GLOBALS['project'] = $project;

                return $project;
            }

            return null;
        }

        $this->db->select('*,' . get_sql_select_client_company());
        $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid=' . db_prefix() . 'projects.clientid');
        $this->db->order_by('id', 'desc');

        return $this->db->get(db_prefix() . 'projects')->result_array();
    }

    public function calculate_total_by_project_hourly_rate($seconds, $hourly_rate)
    {
        $hours = seconds_to_time_format($seconds);
        $decimal = sec2qty($seconds);
        $total_money = 0;
        $total_money += ($decimal * $hourly_rate);

        return [
            'hours' => $hours,
            'total_money' => $total_money,
        ];
    }

    public function calculate_total_by_task_hourly_rate($tasks)
    {
        $total_money = 0;
        $_total_seconds = 0;

        foreach ($tasks as $task) {
            $seconds = $task['total_logged_time'];
            $_total_seconds += $seconds;
            $total_money += sec2qty($seconds) * $task['hourly_rate'];
        }

        return [
            'total_money' => $total_money,
            'total_seconds' => $_total_seconds,
        ];
    }

    public function get_tasks($id, $where = [], $apply_restrictions = false, $count = false, $callback = null)
    {
        $has_permission = staff_can('view',  'tasks');
        $show_all_tasks_for_project_member = get_option('show_all_tasks_for_project_member');

        $select = implode(', ', prefixed_table_fields_array(db_prefix() . 'tasks')) . ',' . db_prefix() . 'milestones.name as milestone_name,
        (SELECT SUM(CASE
            WHEN end_time is NULL THEN ' . time() . '-start_time
            ELSE end_time-start_time
            END) FROM ' . db_prefix() . 'taskstimers WHERE task_id=' . db_prefix() . 'tasks.id) as total_logged_time,
           ' . get_sql_select_task_assignees_ids() . ' as assignees_ids
        ';

        if (!is_client_logged_in() && is_staff_logged_in()) {
            $select .= ',(SELECT staffid FROM ' . db_prefix() . 'task_assigned WHERE taskid=' . db_prefix() . 'tasks.id AND staffid=' . get_staff_user_id() . ') as current_user_is_assigned';
        }

        if (is_client_logged_in()) {
            $this->db->where('visible_to_client', 1);
        }

        $this->db->select($select);

        $this->db->join(db_prefix() . 'milestones', db_prefix() . 'milestones.id = ' . db_prefix() . 'tasks.milestone', 'left');
        $this->db->where('rel_id', $id);
        $this->db->where('rel_type', 'project');
        if ($apply_restrictions == true) {
            if (!is_client_logged_in() && !$has_permission && $show_all_tasks_for_project_member == 0) {
                $this->db->where('(
                    ' . db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid=' . get_staff_user_id() . ')
                    OR ' . db_prefix() . 'tasks.id IN(SELECT taskid FROM ' . db_prefix() . 'task_followers WHERE staffid=' . get_staff_user_id() . ')
                    OR is_public = 1
                    OR (addedfrom =' . get_staff_user_id() . ' AND is_added_from_contact = 0)
                    )');
            }
        }

        if (isset($where[db_prefix() . 'milestones.hide_from_customer'])) {
            $this->db->group_start();
            $this->db->where(db_prefix() . 'milestones.hide_from_customer', $where[db_prefix() . 'milestones.hide_from_customer']);
            $this->db->or_where(db_prefix() . 'tasks.milestone', 0);
            $this->db->group_end();
            unset($where[db_prefix() . 'milestones.hide_from_customer']);
        }

        $this->db->where($where);

        // Milestones kanban order
        // Request is admin/projects/milestones_kanban
        if ($this->uri->segment(3) == 'milestones_kanban' | $this->uri->segment(3) == 'milestones_kanban_load_more') {
            $this->db->order_by('milestone_order', 'asc');
        } else {
            $orderByString = hooks()->apply_filters('project_tasks_array_default_order', 'FIELD(status, 5), duedate IS NULL ASC, duedate');
            $this->db->order_by($orderByString, '', false);
        }

        if ($callback) {
            $callback();
        }

        if ($count == false) {
            $tasks = $this->db->get(db_prefix() . 'tasks')->result_array();
        } else {
            $tasks = $this->db->count_all_results(db_prefix() . 'tasks');
        }

        $tasks = hooks()->apply_filters('get_projects_tasks', $tasks, [
            'project_id' => $id,
            'where' => $where,
            'count' => $count,
        ]);

        return $tasks;
    }

    public function cancel_recurring_tasks($id)
    {
        $this->db->where('rel_type', 'project');
        $this->db->where('rel_id', $id);
        $this->db->where('recurring', 1);
        $this->db->where('(cycles != total_cycles OR cycles=0)');

        $this->db->update(db_prefix() . 'tasks', [
            'recurring_type' => null,
            'repeat_every' => 0,
            'cycles' => 0,
            'recurring' => 0,
            'custom_recurring' => 0,
            'last_recurring_date' => null,
        ]);
    }

    public function do_milestones_kanban_query($milestone_id, $project_id, $page = 1, $where = [], $count = false)
    {
        $where['milestone'] = $milestone_id;
        $limit = get_option('tasks_kanban_limit');
        $tasks = $this->get_tasks($project_id, $where, true, $count, function () use ($count, $page, $limit) {
            if ($count == false) {
                if ($page > 1) {
                    $position = (($page - 1) * $limit);
                    $this->db->limit($limit, $position);
                } else {
                    $this->db->limit($limit);
                }
            }
        });

        return $tasks;
    }

    public function get_files($project_id)
    {
        if (is_client_logged_in()) {
            $this->db->where('visible_to_customer', 1);
        }
        $this->db->where('project_id', $project_id);

        return $this->db->get(db_prefix() . 'project_files')->result_array();
    }

    public function get_file($id, $project_id = false)
    {
        if (is_client_logged_in()) {
            $this->db->where('visible_to_customer', 1);
        }
        $this->db->where('id', $id);
        $file = $this->db->get(db_prefix() . 'project_files')->row();

        if ($file && $project_id) {
            if ($file->project_id != $project_id) {
                return false;
            }
        }

        return $file;
    }

    public function update_file_data($data)
    {
        $this->db->where('id', $data['id']);
        unset($data['id']);
        $this->db->update(db_prefix() . 'project_files', $data);
    }

    public function change_file_visibility($id, $visible)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'project_files', [
            'visible_to_customer' => $visible,
        ]);
    }

    public function change_activity_visibility($id, $visible)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'project_activity', [
            'visible_to_customer' => $visible,
        ]);
    }

    public function remove_file($id, $logActivity = true)
    {
        hooks()->do_action('before_remove_project_file', $id);

        $this->db->where('id', $id);
        $file = $this->db->get(db_prefix() . 'project_files')->row();
        if ($file) {
            if (empty($file->external)) {
                $path = get_upload_path_by_type('project') . $file->project_id . '/';
                $fullPath = $path . $file->file_name;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                    $fname = pathinfo($fullPath, PATHINFO_FILENAME);
                    $fext = pathinfo($fullPath, PATHINFO_EXTENSION);
                    $thumbPath = $path . $fname . '_thumb.' . $fext;

                    if (file_exists($thumbPath)) {
                        unlink($thumbPath);
                    }
                }
            }

            $this->db->where('id', $id);
            $this->db->delete(db_prefix() . 'project_files');
            if ($logActivity) {
                $this->log_activity($file->project_id, 'project_activity_project_file_removed', $file->file_name, $file->visible_to_customer);
            }

            // Delete discussion comments
            $this->_delete_discussion_comments($id, 'file');

            if (is_dir(get_upload_path_by_type('project') . $file->project_id)) {
                // Check if no attachments left, so we can delete the folder also
                $other_attachments = list_files(get_upload_path_by_type('project') . $file->project_id);
                if (count($other_attachments) == 0) {
                    delete_dir(get_upload_path_by_type('project') . $file->project_id);
                }
            }

            return true;
        }

        return false;
    }

    public function calc_milestone_logged_time($project_id, $id)
    {
        $total = [];
        $tasks = $this->get_tasks($project_id, [
            'milestone' => $id,
        ]);

        foreach ($tasks as $task) {
            $total[] = $task['total_logged_time'];
        }

        return array_sum($total);
    }

    public function total_logged_time($id)
    {
        $q = $this->db->query('
            SELECT SUM(CASE
                WHEN end_time is NULL THEN ' . time() . '-start_time
                ELSE end_time-start_time
                END) as total_logged_time
            FROM ' . db_prefix() . 'taskstimers
            WHERE task_id IN (SELECT id FROM ' . db_prefix() . 'tasks WHERE rel_type="project" AND rel_id=' . $this->db->escape_str($id) . ')')
            ->row();

        return $q->total_logged_time;
    }

    public function get_milestones($project_id, $where = [])
    {
        $this->db->select('*, (SELECT COUNT(id) FROM ' . db_prefix() . 'tasks WHERE rel_type="project" AND rel_id=' . $this->db->escape_str($project_id) . ' and milestone=' . db_prefix() . 'milestones.id) as total_tasks, (SELECT COUNT(id) FROM ' . db_prefix() . 'tasks WHERE rel_type="project" AND rel_id=' . $this->db->escape_str($project_id) . ' and milestone=' . db_prefix() . 'milestones.id AND status=5) as total_finished_tasks');
        $this->db->where('project_id', $project_id);
        $this->db->order_by('milestone_order', 'ASC');
        $this->db->where($where);
        $milestones = $this->db->get(db_prefix() . 'milestones')->result_array();
        $i = 0;
        foreach ($milestones as $milestone) {
            $milestones[$i]['total_logged_time'] = $this->calc_milestone_logged_time($project_id, $milestone['id']);
            $i++;
        }


        return $milestones;
    }

    public function add_milestone($data)
    {
        $data['due_date'] = to_sql_date($data['due_date']);
        $data['start_date'] = to_sql_date($data['start_date']);
        $data['datecreated'] = date('Y-m-d');
        $data['description'] = nl2br($data['description']);
        $data['description_visible_to_customer'] = isset($data['description_visible_to_customer']) ? 1 : 0;
        $data['hide_from_customer'] = isset($data['hide_from_customer']) ? 1 : 0;

        $this->db->insert(db_prefix() . 'milestones', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            $this->db->where('id', $insert_id);
            $milestone = $this->db->get(db_prefix() . 'milestones')->row();
            $project = $this->get($milestone->project_id);
            if ($project->settings->view_milestones == 1) {
                $show_to_customer = 1;
            } else {
                $show_to_customer = 0;
            }
            $this->log_activity($milestone->project_id, 'project_activity_created_milestone', $milestone->name, $show_to_customer);
            log_activity('Project Milestone Created [ID:' . $insert_id . ']');

            return $insert_id;
        }

        return false;
    }

    public function update_milestone($data, $id)
    {
        $this->db->where('id', $id);
        $milestone = $this->db->get(db_prefix() . 'milestones')->row();
        $data['due_date'] = to_sql_date($data['due_date']);
        $data['start_date'] = to_sql_date($data['start_date']);
        $data['description'] = nl2br($data['description']);
        $data['description_visible_to_customer'] = isset($data['description_visible_to_customer']) ? 1 : 0;
        $data['hide_from_customer'] = isset($data['hide_from_customer']) ? 1 : 0;

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'milestones', $data);
        if ($this->db->affected_rows() > 0) {
            $project = $this->get($milestone->project_id);
            if ($project->settings->view_milestones == 1) {
                $show_to_customer = 1;
            } else {
                $show_to_customer = 0;
            }
            $this->log_activity($milestone->project_id, 'project_activity_updated_milestone', $milestone->name, $show_to_customer);
            log_activity('Project Milestone Updated [ID:' . $id . ']');

            return true;
        }

        return false;
    }

    public function update_task_milestone($data)
    {
        $this->db->where('id', $data['task_id']);
        $this->db->update(db_prefix() . 'tasks', [
            'milestone' => $data['milestone_id'],
        ]);

        foreach ($data['order'] as $order) {
            $this->db->where('id', $order[0]);
            $this->db->update(db_prefix() . 'tasks', [
                'milestone_order' => $order[1],
            ]);
        }
    }

    public function update_milestones_order($data)
    {
        foreach ($data['order'] as $status) {
            $this->db->where('id', $status[0]);
            $this->db->update(db_prefix() . 'milestones', [
                'milestone_order' => $status[1],
            ]);
        }
    }

    public function update_milestone_color($data)
    {
        $this->db->where('id', $data['milestone_id']);
        $this->db->update(db_prefix() . 'milestones', [
            'color' => $data['color'],
        ]);
    }

    public function delete_milestone($id)
    {
        $this->db->where('id', $id);
        $milestone = $this->db->get(db_prefix() . 'milestones')->row();
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'milestones');
        if ($this->db->affected_rows() > 0) {
            $project = $this->get($milestone->project_id);
            if ($project->settings->view_milestones == 1) {
                $show_to_customer = 1;
            } else {
                $show_to_customer = 0;
            }
            $this->log_activity($milestone->project_id, 'project_activity_deleted_milestone', $milestone->name, $show_to_customer);
            $this->db->where('milestone', $id);
            $this->db->update(db_prefix() . 'tasks', [
                'milestone' => 0,
            ]);
            log_activity('Project Milestone Deleted [' . $id . ']');

            return true;
        }

        return false;
    }

    public function add($data)
    {
        $detail_data = [];
        if (isset($data['newitems'])) {
            $detail_data = $data['newitems'];
            unset($data['newitems']);
        }
        unset($data['item_text']);
        unset($data['unit_price']);
        unset($data['quantity']);
        unset($data['into_money']);
        unset($data['tax_select']);
        unset($data['tax_value']);
        unset($data['total']);
        unset($data['item_select']);
        unset($data['item_code']);
        unset($data['unit_name']);
        unset($data['request_detail']);
        unset($data['unit_id']);

        if (isset($data['send_to_vendors']) && count($data['send_to_vendors']) > 0) {
            $data['send_to_vendors'] = implode(',', $data['send_to_vendors']);
        }

        $data['subtotal'] = reformat_currency_pur($data['subtotal'], $data['currency']);

        if (isset($data['total_mn'])) {
            $data['total'] = reformat_currency_pur($data['total_mn'], $data['currency']);
            unset($data['total_mn']);
        }

        $data['total_tax'] = $data['total'] - $data['subtotal'];


        // $dpm_name = department_pur_request_name($data['department']);
        $prefix = get_purchase_option('project_prefix');

        if (isset($data['notify_project_members_status_change'])) {
            unset($data['notify_project_members_status_change']);
        }
        $send_created_email = false;
        if (isset($data['send_created_email'])) {
            unset($data['send_created_email']);
            $send_created_email = true;
        }

        $send_project_marked_as_finished_email_to_contacts = false;
        if (isset($data['project_marked_as_finished_email_to_contacts'])) {
            unset($data['project_marked_as_finished_email_to_contacts']);
            $send_project_marked_as_finished_email_to_contacts = true;
        }

        if (isset($data['settings'])) {
            $project_settings = $data['settings'];
            unset($data['settings']);
        }
        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            unset($data['custom_fields']);
        }
        if (isset($data['progress_from_tasks'])) {
            $data['progress_from_tasks'] = 1;
        } else {
            $data['progress_from_tasks'] = 0;
        }

        if (isset($data['contact_notification'])) {
            if ($data['contact_notification'] == 2) {
                $data['notify_contacts'] = serialize($data['notify_contacts']);
            } else {
                $data['notify_contacts'] = serialize([]);
            }
        }

        $data['project_cost'] = !empty($data['project_cost']) ? $data['project_cost'] : null;
        $data['estimated_hours'] = !empty($data['estimated_hours']) ? $data['estimated_hours'] : null;

        $data['start_date'] = to_sql_date($data['start_date']);

        if (!empty($data['deadline'])) {
            $data['deadline'] = to_sql_date($data['deadline']);
        } else {
            unset($data['deadline']);
        }

        $data['project_created'] = date('Y-m-d');
        if (isset($data['project_members'])) {
            $project_members = $data['project_members'];
            unset($data['project_members']);
        }
        if ($data['billing_type'] == 1) {
            $data['project_rate_per_hour'] = 0;
        } elseif ($data['billing_type'] == 2) {
            $data['project_cost'] = 0;
        } else {
            $data['project_rate_per_hour'] = 0;
            $data['project_cost'] = 0;
        }

        $data['addedfrom'] = get_staff_user_id();


        $items_to_convert = false;
        if (isset($data['items'])) {
            $items_to_convert = $data['items'];
            $estimate_id = $data['estimate_id'];
            $items_assignees = $data['items_assignee'];
            unset($data['items'], $data['estimate_id'], $data['items_assignee']);
        }

        $data = hooks()->apply_filters('before_add_project', $data);

        $tags = '';
        if (isset($data['tags'])) {
            $tags = $data['tags'];
            unset($data['tags']);
        }

        $this->db->insert(db_prefix() . 'projects', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            handle_tags_save($tags, $insert_id, 'project');

            if (isset($custom_fields)) {
                handle_custom_fields_post($insert_id, $custom_fields);
            }

            if (isset($project_members)) {
                $_pm['project_members'] = $project_members;
                $this->add_edit_members($_pm, $insert_id);
            }

            $original_settings = $this->get_settings();
            if (isset($project_settings)) {
                $_settings = [];
                $_values = [];
                foreach ($project_settings as $name => $val) {
                    array_push($_settings, $name);
                    $_values[$name] = $val;
                }
                foreach ($original_settings as $setting) {
                    if ($setting != 'available_features') {
                        if (in_array($setting, $_settings)) {
                            $value_setting = 1;
                        } else {
                            $value_setting = 0;
                        }
                    } else {
                        $tabs = get_project_tabs_admin();
                        $tab_settings = [];
                        foreach ($_values[$setting] as $tab) {
                            $tab_settings[$tab] = 1;
                        }
                        foreach ($tabs as $tab) {
                            if (!isset($tab['collapse'])) {
                                if (!in_array($tab['slug'], $_values[$setting])) {
                                    $tab_settings[$tab['slug']] = 0;
                                }
                            } else {
                                foreach ($tab['children'] as $tab_dropdown) {
                                    if (!in_array($tab_dropdown['slug'], $_values[$setting])) {
                                        $tab_settings[$tab_dropdown['slug']] = 0;
                                    }
                                }
                            }
                        }
                        $value_setting = serialize($tab_settings);
                    }
                    $this->db->insert(db_prefix() . 'project_settings', [
                        'project_id' => $insert_id,
                        'name' => $setting,
                        'value' => $value_setting,
                    ]);
                }
            } else {
                foreach ($original_settings as $setting) {
                    $value_setting = 0;
                    $this->db->insert(db_prefix() . 'project_settings', [
                        'project_id' => $insert_id,
                        'name' => $setting,
                        'value' => $value_setting,
                    ]);
                }
            }

            if ($items_to_convert && is_numeric($estimate_id)) {
                $this->convert_estimate_items_to_tasks($insert_id, $items_to_convert, $items_assignees, $data, $project_settings);

                $this->db->where('id', $estimate_id);
                $this->db->set('project_id', $insert_id);
                $this->db->update(db_prefix() . 'estimates');
            }

            $this->log_activity($insert_id, 'project_activity_created');

            if ($send_created_email == true) {
                $this->send_project_customer_email($insert_id, 'project_created_to_customer');
            }

            if ($send_project_marked_as_finished_email_to_contacts == true) {
                $this->send_project_customer_email($insert_id, 'project_marked_as_finished_to_customer');
            }

            hooks()->do_action('after_add_project', $insert_id);

            log_activity('New Project Created [ID: ' . $insert_id . ']');

            // detail project

            // Update next purchase order number in settings
            $next_number = $data['number'] + 1;
            $this->db->where('option_name', 'next_prj_number');
            $this->db->update(db_prefix() . 'purchase_option', ['option_val' =>  $next_number,]);

            if (count($detail_data) > 0) {
                foreach ($detail_data as $key => $rqd) {
                    $dt_data = [];
                    $dt_data['project_id'] = $insert_id;
                    $dt_data['item_code'] = $rqd['item_code'];
                    $dt_data['unit_id'] = isset($rqd['unit_id']) ? $rqd['unit_id'] : null;
                    $dt_data['unit_price'] = $rqd['unit_price'];
                    $dt_data['into_money'] = $rqd['into_money'];
                    $dt_data['total'] = $rqd['total'];
                    $dt_data['tax_value'] = $rqd['tax_value'];
                    $dt_data['item_text'] = nl2br($rqd['item_text']);

                    $tax_money = 0;
                    $tax_rate_value = 0;
                    $tax_rate = null;
                    $tax_id = null;
                    $tax_name = null;

                    if (isset($rqd['tax_select'])) {
                        $tax_rate_data = $this->pur_get_tax_rate($rqd['tax_select']);
                        $tax_rate_value = $tax_rate_data['tax_rate'];
                        $tax_rate = $tax_rate_data['tax_rate_str'];
                        $tax_id = $tax_rate_data['tax_id_str'];
                        $tax_name = $tax_rate_data['tax_name_str'];
                    }

                    $dt_data['tax'] = $tax_id;
                    $dt_data['tax_rate'] = $tax_rate;
                    $dt_data['tax_name'] = $tax_name;

                    $dt_data['quantity'] = ($rqd['quantity'] != '' && $rqd['quantity'] != null) ? $rqd['quantity'] : 0;

                    if ($data['status'] == 2 && ($rqd['item_code'] == '' || $rqd['item_code'] == null)) {
                        $item_data['description'] = $rqd['item_text'];
                        $item_data['purchase_price'] = $rqd['unit_price'];
                        $item_data['unit_id'] = $rqd['unit_id'];
                        $item_data['rate'] = '';
                        $item_data['sku_code'] = '';
                        $item_data['commodity_barcode'] = $this->generate_commodity_barcode();
                        $item_data['commodity_code'] = $this->generate_commodity_barcode();
                        $item_id = $this->add_commodity_one_item($item_data);
                        if ($item_id) {
                            $dt_data['item_code'] = $item_id;
                        }
                    }

                    $this->db->insert(db_prefix() . 'project_detail', $dt_data);
                }
            }

            return $insert_id;
        }

        return false;
    }

    public function update($data, $id)
    {
        $affectedRows = 0;
        $purq = $this->get_projects($id);

        $data['subtotal'] = reformat_currency_pur($data['subtotal'], $data['currency']);

        $data['to_currency'] = $data['currency'];

        $new_project = [];
        if (isset($data['newitems'])) {
            $new_project = $data['newitems'];
            unset($data['newitems']);
        }

        $update_project = [];
        if (isset($data['items'])) {
            $update_project = $data['items'];
            unset($data['items']);
        }

        $remove_project = [];
        if (isset($data['removed_items'])) {
            $remove_project = $data['removed_items'];
            unset($data['removed_items']);
        }

        unset($data['item_text']);
        unset($data['unit_price']);
        unset($data['quantity']);
        unset($data['into_money']);
        unset($data['tax_select']);
        unset($data['tax_value']);
        unset($data['total']);
        unset($data['item_select']);
        unset($data['item_code']);
        unset($data['unit_name']);
        unset($data['request_detail']);
        unset($data['isedit']);
        unset($data['unit_id']);

        if (isset($data['send_to_vendors']) && count($data['send_to_vendors']) > 0) {
            $data['send_to_vendors'] = implode(',', $data['send_to_vendors']);
        }

       

        if (isset($data['total_mn'])) {
            $data['total'] = reformat_currency_pur($data['total_mn'], $data['currency']);
            unset($data['total_mn']);
        }
        $data['total_tax'] = $data['total'] - $data['subtotal'];

        $this->db->select('status');
        $this->db->where('id', $id);
        $old_status = $this->db->get(db_prefix() . 'projects')->row()->status;

        $send_created_email = false;
        if (isset($data['send_created_email'])) {
            unset($data['send_created_email']);
            $send_created_email = true;
        }

        $send_project_marked_as_finished_email_to_contacts = false;
        if (isset($data['project_marked_as_finished_email_to_contacts'])) {
            unset($data['project_marked_as_finished_email_to_contacts']);
            $send_project_marked_as_finished_email_to_contacts = true;
        }

        $original_project = $this->get($id);

        if (isset($data['notify_project_members_status_change'])) {
            $notify_project_members_status_change = true;
            unset($data['notify_project_members_status_change']);
        }
        $affectedRows = 0;
        if (!isset($data['settings'])) {
            $this->db->where('project_id', $id);
            $this->db->update(db_prefix() . 'project_settings', [
                'value' => 0,
            ]);
            if ($this->db->affected_rows() > 0) {
                $affectedRows++;
            }
        } else {
            $_settings = [];
            $_values = [];

            foreach ($data['settings'] as $name => $val) {
                array_push($_settings, $name);
                $_values[$name] = $val;
            }

            unset($data['settings']);
            $original_settings = $this->get_project_settings($id);

            foreach ($original_settings as $setting) {
                if ($setting['name'] != 'available_features') {
                    if (in_array($setting['name'], $_settings)) {
                        $value_setting = 1;
                    } else {
                        $value_setting = 0;
                    }
                } else {
                    $tabs = get_project_tabs_admin();
                    $tab_settings = [];
                    foreach ($_values[$setting['name']] as $tab) {
                        $tab_settings[$tab] = 1;
                    }
                    foreach ($tabs as $tab) {
                        if (!isset($tab['collapse'])) {
                            if (!in_array($tab['slug'], $_values[$setting['name']])) {
                                $tab_settings[$tab['slug']] = 0;
                            }
                        } else {
                            foreach ($tab['children'] as $tab_dropdown) {
                                if (!in_array($tab_dropdown['slug'], $_values[$setting['name']])) {
                                    $tab_settings[$tab_dropdown['slug']] = 0;
                                }
                            }
                        }
                    }
                    $value_setting = serialize($tab_settings);
                }

                $this->db->where('project_id', $id);
                $this->db->where('name', $setting['name']);
                $this->db->update(db_prefix() . 'project_settings', [
                    'value' => $value_setting,
                ]);

                if ($this->db->affected_rows() > 0) {
                    $affectedRows++;
                }
            }
        }

        $data['project_cost'] = !empty($data['project_cost']) ? $data['project_cost'] : null;
        $data['estimated_hours'] = !empty($data['estimated_hours']) ? $data['estimated_hours'] : null;

        if ($old_status == 4 && $data['status'] != 4) {
            $data['date_finished'] = null;
        } elseif (isset($data['date_finished'])) {
            $data['date_finished'] = to_sql_date($data['date_finished'], true);
        }

        if (isset($data['progress_from_tasks'])) {
            $data['progress_from_tasks'] = 1;
        } else {
            $data['progress_from_tasks'] = 0;
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            if (handle_custom_fields_post($id, $custom_fields)) {
                $affectedRows++;
            }
            unset($data['custom_fields']);
        }

        if (!empty($data['deadline'])) {
            $data['deadline'] = to_sql_date($data['deadline']);
        } else {
            $data['deadline'] = null;
        }

        $data['start_date'] = to_sql_date($data['start_date']);
        if ($data['billing_type'] == 1) {
            $data['project_rate_per_hour'] = 0;
        } elseif ($data['billing_type'] == 2) {
            $data['project_cost'] = 0;
        } else {
            $data['project_rate_per_hour'] = 0;
            $data['project_cost'] = 0;
        }
        if (isset($data['project_members'])) {
            $project_members = $data['project_members'];
            unset($data['project_members']);
        }
        $_pm = [];
        if (isset($project_members)) {
            $_pm['project_members'] = $project_members;
        }
        if ($this->add_edit_members($_pm, $id)) {
            $affectedRows++;
        }
        if (isset($data['mark_all_tasks_as_completed'])) {
            $mark_all_tasks_as_completed = true;
            unset($data['mark_all_tasks_as_completed']);
        }

        if (isset($data['tags'])) {
            if (handle_tags_save($data['tags'], $id, 'project')) {
                $affectedRows++;
            }
            unset($data['tags']);
        }

        if (isset($data['cancel_recurring_tasks'])) {
            unset($data['cancel_recurring_tasks']);
            $this->cancel_recurring_tasks($id);
        }

        if (isset($data['contact_notification'])) {
            if ($data['contact_notification'] == 2) {
                $data['notify_contacts'] = serialize($data['notify_contacts']);
            } else {
                $data['notify_contacts'] = serialize([]);
            }
        }

        $data = hooks()->apply_filters('before_update_project', $data, $id);

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'projects', $data);

        if ($this->db->affected_rows() > 0) {
            if (isset($mark_all_tasks_as_completed)) {
                $this->_mark_all_project_tasks_as_completed($id);
            }
            $affectedRows++;
        }

        if ($send_created_email == true) {
            if ($this->send_project_customer_email($id, 'project_created_to_customer')) {
                $affectedRows++;
            }
        }

        if ($send_project_marked_as_finished_email_to_contacts == true) {
            if ($this->send_project_customer_email($id, 'project_marked_as_finished_to_customer')) {
                $affectedRows++;
            }
        }
        if ($affectedRows > 0) {
            $this->log_activity($id, 'project_activity_updated');
            log_activity('Project Updated [ID: ' . $id . ']');

            if ($original_project->status != $data['status']) {
                hooks()->do_action('project_status_changed', [
                    'status' => $data['status'],
                    'project_id' => $id,
                ]);
                // Give space this log to be on top
                sleep(1);
                if ($data['status'] == 4) {
                    $this->log_activity($id, 'project_marked_as_finished');
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'projects', ['date_finished' => date('Y-m-d H:i:s')]);
                } else {
                    $this->log_activity($id, 'project_status_updated', '<b><lang>project_status_' . $data['status'] . '</lang></b>');
                }

                if (isset($notify_project_members_status_change)) {
                    $this->_notify_project_members_status_change($id, $original_project->status, $data['status']);
                }
            }
            hooks()->do_action('after_update_project', $id);

            return true;
        }

        if (count($update_project) > 0) {
            foreach ($update_project as $_key => $rqd) {
                $dt_data = [];
                $dt_data['project_id'] = $id;
                $dt_data['item_code'] = $rqd['item_code'];
                $dt_data['unit_id'] = isset($rqd['unit_id']) ? $rqd['unit_id'] : null;
                $dt_data['unit_price'] = $rqd['unit_price'];
                $dt_data['into_money'] = $rqd['into_money'];
                $dt_data['total'] = $rqd['total'];
                $dt_data['tax_value'] = $rqd['tax_value'];
                $dt_data['item_text'] = nl2br($rqd['item_text']);

                $tax_money = 0;
                $tax_rate_value = 0;
                $tax_rate = null;
                $tax_id = null;
                $tax_name = null;

                if (isset($rqd['tax_select'])) {
                    $tax_rate_data = $this->pur_get_tax_rate($rqd['tax_select']);
                    $tax_rate_value = $tax_rate_data['tax_rate'];
                    $tax_rate = $tax_rate_data['tax_rate_str'];
                    $tax_id = $tax_rate_data['tax_id_str'];
                    $tax_name = $tax_rate_data['tax_name_str'];
                }

                $dt_data['tax'] = $tax_id;
                $dt_data['tax_rate'] = $tax_rate;
                $dt_data['tax_name'] = $tax_name;

                $dt_data['quantity'] = ($rqd['quantity'] != '' && $rqd['quantity'] != null) ? $rqd['quantity'] : 0;

                if ($purq->status == 2 && ($rqd['item_code'] == '' || $rqd['item_code'] == null)) {
                    $item_data['description'] = $rqd['item_text'];
                    $item_data['purchase_price'] = $rqd['unit_price'];
                    $item_data['unit_id'] = $rqd['unit_id'];
                    $item_data['rate'] = '';
                    $item_data['sku_code'] = '';
                    $item_data['commodity_barcode'] = $this->generate_commodity_barcode();
                    $item_data['commodity_code'] = $this->generate_commodity_barcode();
                    $item_id = $this->add_commodity_one_item($item_data);
                    if ($item_id) {
                        $dt_data['item_code'] = $item_id;
                    }
                }

                $this->db->where('prd_id', $rqd['id']);
                $this->db->update(db_prefix() . 'project_detail', $dt_data);
                if ($this->db->affected_rows() > 0) {
                    $affectedRows++;
                }
            }
        }

        if (count($remove_project) > 0) {
            foreach ($remove_project as $remove_id) {
                $this->db->where('prd_id', $remove_id);
                if ($this->db->delete(db_prefix() . 'project_detail')) {
                    $affectedRows++;
                }
            }
        }

        return false;
    }

    public function get_project_detail($project)
    {
        $this->db->where('project_id', $project);
        $project_lst = $this->db->get(db_prefix() . 'project_detail')->result_array();

        foreach ($project_lst as $key => $detail) {
            $project_lst[$key]['into_money'] = (float) $detail['into_money'];
            $project_lst[$key]['total'] = (float) $detail['total'];
            $project_lst[$key]['unit_price'] = (float) $detail['unit_price'];
            $project_lst[$key]['tax_value'] = (float) $detail['tax_value'];
        }

        return $project_lst;
    }

    public function create_project_row_template($name = '', $item_code = '', $item_text = '', $unit_price = '', $quantity = '', $unit_name = '', $unit_id = '', $into_money = '', $item_key = '', $tax_value = '', $total = '', $tax_name = '', $tax_rate = '', $tax_id = '', $is_edit = false, $currency_rate = 1, $to_currency = '')
    {
        $this->load->model('invoice_items_model');
        $row = '';

        $name_item_code = 'item_code';
        $name_item_text = 'item_text';
        $name_unit_id = 'unit_id';
        $name_unit_name = 'unit_name';
        $name_unit_price = 'unit_price';
        $name_quantity = 'quantity';
        $name_into_money = 'into_money';
        $name_tax = 'tax';
        $name_tax_value = 'tax_value';
        $name_tax_name = 'tax_name';
        $name_tax_rate = 'tax_rate';
        $name_tax_id_select = 'tax_select';
        $name_total = 'total';

        $array_rate_attr = ['min' => '0.0', 'step' => 'any'];
        $array_qty_attr = ['min' => '0.0', 'step' => 'any'];
        $array_subtotal_attr = ['readonly' => true];

        $text_right_class = 'text-right';

        if ($name == '') {
            $row .= '<tr class="main">
                  <td></td>';
            $vehicles = [];
            $array_attr = ['placeholder' => _l('unit_price')];

            $manual             = true;
            $invoice_item_taxes = '';
            $total = '';
            $into_money = 0;
        } else {
            $manual             = false;
            $row .= '<tr class="sortable item">
                    <td class="dragger"><input type="hidden" class="order" name="' . $name . '[order]"><input type="hidden" class="ids" name="' . $name . '[id]" value="' . $item_key . '"></td>';
            $name_item_code = $name . '[item_code]';
            $name_item_text = $name . '[item_text]';
            $name_unit_id = $name . '[unit_id]';
            $name_unit_name = $name . '[unit_name]';
            $name_unit_price = $name . '[unit_price]';
            $name_quantity = $name . '[quantity]';
            $name_into_money = $name . '[into_money]';
            $name_tax = $name . '[tax]';
            $name_tax_value = $name . '[tax_value]';
            $name_tax_name = $name . '[tax_name]';
            $name_tax_rate = $name . '[tax_rate]';
            $name_tax_id_select = $name . '[tax_select][]';
            $name_total = $name . '[total]';

            $array_rate_attr = ['onblur' => 'pur_calculate_total();', 'onchange' => 'pur_calculate_total();', 'min' => '0.0', 'step' => 'any', 'data-amount' => 'invoice', 'placeholder' => _l('unit_price')];

            $array_qty_attr = ['onblur' => 'pur_calculate_total();', 'onchange' => 'pur_calculate_total();', 'min' => '0.0', 'step' => 'any',  'data-quantity' => (float)$quantity];

            $tax_money = 0;
            $tax_rate_value = 0;

            if ($is_edit) {
                $invoice_item_taxes = pur_convert_item_taxes($tax_id, $tax_rate, $tax_name);
                $arr_tax_rate = explode('|', $tax_rate ?? '');
                foreach ($arr_tax_rate as $key => $value) {
                    $tax_rate_value += (float)$value;
                }
            } else {
                $invoice_item_taxes = $tax_name;
                $tax_rate_data = $this->pur_get_tax_rate($tax_name);
                $tax_rate_value = $tax_rate_data['tax_rate'];
            }

            if ((float)$tax_rate_value != 0) {
                $tax_money = (float)$unit_price * (float)$quantity * (float)$tax_rate_value / 100;

                $amount = (float)$unit_price * (float)$quantity + (float)$tax_money;
            } else {

                $amount = (float)$unit_price * (float)$quantity;
            }

            $into_money = (float)$unit_price * (float)$quantity;
            $total = $amount;
        }


        $row .= '<td class="">' . render_textarea($name_item_text, '', $item_text, ['rows' => 2, 'placeholder' => _l('pur_item_name')]) . '</td>';
        $row .= '<td class="rate">' . render_input($name_unit_price, '', $unit_price, 'number', $array_rate_attr, [], 'no-margin', $text_right_class);
        if ($unit_price != '') {
            $original_price = round(($unit_price / $currency_rate), 2);
            $base_currency = get_base_currency();
            if ($to_currency != 0 && $to_currency != $base_currency->id) {
                $row .= render_input('original_price', '', app_format_money($original_price, $base_currency), 'text', ['data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => _l('original_price'), 'disabled' => true], [], 'no-margin', 'input-transparent text-right pur_input_none');
            }

            $row .= '<input class="hide" name="og_price" disabled="true" value="' . $original_price . '">';
        }

        $row .=  '</td>';

        $row .= '<td class="quantities">' .
            render_input($name_quantity, '', $quantity, 'number', $array_qty_attr, [], 'no-margin', $text_right_class) .
            render_input($name_unit_name, '', $unit_name, 'text', ['placeholder' => _l('unit'), 'readonly' => true], [], 'no-margin', 'input-transparent text-right pur_input_none') .
            '</td>';

        $row .= '<td class="into_money">' . render_input($name_into_money, '', $into_money, 'number', $array_subtotal_attr, [], '', $text_right_class) . '</td>';
        $row .= '<td class="taxrate">' . $this->get_taxes_dropdown_template($name_tax_id_select, $invoice_item_taxes, 'invoice', $item_key, true, $manual) . '</td>';
        $row .= '<td class="tax_value">' . render_input($name_tax_value, '', $tax_value, 'number', $array_subtotal_attr, [], '', $text_right_class) . '</td>';
        $row .= '<td class="hide item_code">' . render_input($name_item_code, '', $item_code, 'text', ['placeholder' => _l('item_code')]) . '</td>';
        $row .= '<td class="hide unit_id">' . render_input($name_unit_id, '', $unit_id, 'text', ['placeholder' => _l('unit_id')]) . '</td>';
        $row .= '<td class="_total">' . render_input($name_total, '', $total, 'number', $array_subtotal_attr, [], '', $text_right_class) . '</td>';

        if ($name == '') {
            $row .= '<td><button type="button" onclick="pur_add_item_to_table(\'undefined\',\'undefined\'); return false;" class="btn pull-right btn-info"><i class="fa fa-check"></i></button></td>';
        } else {
            $row .= '<td><a href="#" class="btn btn-danger pull-right" onclick="pur_delete_item(this,' . $item_key . ',\'.invoice-item\'); return false;"><i class="fa fa-trash"></i></a></td>';
        }
        $row .= '</tr>';
        return $row;
    }

    public function get_taxes_dropdown_template($name, $taxname, $type = '', $item_key = '', $is_edit = false, $manual = false)
    {
        // if passed manually - like in proposal convert items or project
        if ($taxname != '' && !is_array($taxname)) {
            $taxname = explode(',', $taxname);
        }

        if ($manual == true) {
            // + is no longer used and is here for backward compatibilities
            if (is_array($taxname) || strpos($taxname, '+') !== false) {
                if (!is_array($taxname)) {
                    $__tax = explode('+', $taxname);
                } else {
                    $__tax = $taxname;
                }
                // Multiple taxes found // possible option from default settings when invoicing project
                $taxname = [];
                foreach ($__tax as $t) {
                    $tax_array = explode('|', $t);
                    if (isset($tax_array[0]) && isset($tax_array[1])) {
                        array_push($taxname, $tax_array[0] . '|' . $tax_array[1]);
                    }
                }
            } else {
                $tax_array = explode('|', $taxname);
                // isset tax rate
                if (isset($tax_array[0]) && isset($tax_array[1])) {
                    $tax = get_tax_by_name($tax_array[0]);
                    if ($tax) {
                        $taxname = $tax->name . '|' . $tax->taxrate;
                    }
                }
            }
        }
        // First get all system taxes
        $this->load->model('taxes_model');
        $taxes = $this->taxes_model->get();
        $i     = 0;
        foreach ($taxes as $tax) {
            unset($taxes[$i]['id']);
            $taxes[$i]['name'] = $tax['name'] . '|' . $tax['taxrate'];
            $i++;
        }
        if ($is_edit == true) {

            // Lets check the items taxes in case of changes.
            // Separate functions exists to get item taxes for Invoice, Estimate, Proposal, Credit Note
            $func_taxes = 'get_' . $type . '_item_taxes';
            if (function_exists($func_taxes)) {
                $item_taxes = call_user_func($func_taxes, $item_key);
            }

            foreach ($item_taxes as $item_tax) {
                $new_tax            = [];
                $new_tax['name']    = $item_tax['taxname'];
                $new_tax['taxrate'] = $item_tax['taxrate'];
                $taxes[]            = $new_tax;
            }
        }

        // In case tax is changed and the old tax is still linked to estimate/proposal when converting
        // This will allow the tax that don't exists to be shown on the dropdowns too.
        if (is_array($taxname)) {
            foreach ($taxname as $tax) {
                // Check if tax empty
                if ((!is_array($tax) && $tax == '') || is_array($tax) && $tax['taxname'] == '') {
                    continue;
                };
                // Check if really the taxname NAME|RATE don't exists in all taxes
                if (!value_exists_in_array_by_key($taxes, 'name', $tax)) {
                    if (!is_array($tax)) {
                        $tmp_taxname = $tax;
                        $tax_array   = explode('|', $tax);
                    } else {
                        $tax_array   = explode('|', $tax['taxname']);
                        $tmp_taxname = $tax['taxname'];
                        if ($tmp_taxname == '') {
                            continue;
                        }
                    }
                    $taxes[] = ['name' => $tmp_taxname, 'taxrate' => $tax_array[1]];
                }
            }
        }

        // Clear the duplicates
        $taxes = $this->pur_uniqueByKey($taxes, 'name');

        $select = '<select class="selectpicker display-block taxes" data-width="100%" name="' . $name . '" multiple data-none-selected-text="' . _l('no_tax') . '">';

        foreach ($taxes as $tax) {
            $selected = '';
            if (is_array($taxname)) {
                foreach ($taxname as $_tax) {
                    if (is_array($_tax)) {
                        if ($_tax['taxname'] == $tax['name']) {
                            $selected = 'selected';
                        }
                    } else {
                        if ($_tax == $tax['name']) {
                            $selected = 'selected';
                        }
                    }
                }
            } else {
                if ($taxname == $tax['name']) {
                    $selected = 'selected';
                }
            }

            $select .= '<option value="' . $tax['name'] . '" ' . $selected . ' data-taxrate="' . $tax['taxrate'] . '" data-taxname="' . $tax['name'] . '" data-subtext="' . $tax['name'] . '">' . $tax['taxrate'] . '%</option>';
        }
        $select .= '</select>';

        return $select;
    }
    public function pur_uniqueByKey($array, $key)
    {
        $temp_array = [];
        $i          = 0;
        $key_array  = [];

        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i]  = $val[$key];
                $temp_array[$i] = $val;
            }
            $i++;
        }

        return $temp_array;
    }

    public function get_projects($id = '')
    {
        if ($id == '') {
            if (!has_permission('project', '', 'view') && is_staff_logged_in()) {

                $or_where = '';
                $list_vendor = get_vendor_admin_list(get_staff_user_id());
                foreach ($list_vendor as $vendor_id) {
                    $or_where .= ' OR find_in_set(' . $vendor_id . ', ' . db_prefix() . 'projects.send_to_vendors)';
                }
                $this->db->where('(' . db_prefix() . 'projects.requester = ' . get_staff_user_id() .  $or_where . ')');
            }

            return $this->db->get(db_prefix() . 'projects')->result_array();
        } else {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'projects')->row();
        }
    }
    public function tax_rate_by_id($tax_id)
    {
        $this->db->where('id', $tax_id);
        $tax = $this->db->get(db_prefix() . 'taxes')->row();
        if ($tax) {
            return $tax->taxrate;
        }
        return 0;
    }
    public function get_tax_name($tax)
    {
        $this->db->where('id', $tax);
        $tax_if = $this->db->get(db_prefix() . 'taxes')->row();
        if ($tax_if) {
            return $tax_if->name;
        }
        return '';
    }

    public function get_sum_pur_orders($id)
    {

        $this->db->select('SUM(pj.project_cost) as totalcost');
        $this->db->from(db_prefix() . 'pur_orders po');
        $this->db->join(db_prefix() . 'projects pj', 'po.project = pj.id');
        $this->db->where('pj.id', $id);
        $query = $this->db->get();
        return $query->row_array(); // Mengembalikan satu baris hasil
    }

    public function get_sum_pur_request($id)
    {

        $this->db->select('SUM(pj.project_cost) as totalcost');
        $this->db->from(db_prefix() . 'pur_request po');
        $this->db->join(db_prefix() . 'projects pj', 'po.project = pj.id');
        $this->db->where('pj.id', $id);
        $query = $this->db->get();
        return $query->row_array(); // Mengembalikan satu baris hasil
    }

    public function get_html_tax_project($id)
    {
        $html = '';
        $preview_html = '';
        $pdf_html = '';
        $taxes = [];
        $t_rate = [];
        $tax_val = [];
        $tax_val_rs = [];
        $tax_name = [];
        $rs = [];

        $request = $this->get_projects($id);

        $this->load->model('currencies_model');
        $base_currency = $this->currencies_model->get_base_currency();
        if ($request->currency != 0 && $request->currency != null) {
            $base_currency = pur_get_currency_by_id($request->currency);
        }

        $this->db->where('project_id', $id);
        $details = $this->db->get(db_prefix() . 'project_detail')->result_array();
        foreach ($details as $row) {
            if ($row['tax'] != '') {
                $tax_arr = explode('|', $row['tax']);

                $tax_rate_arr = [];
                if ($row['tax_rate'] != '') {
                    $tax_rate_arr = explode('|', $row['tax_rate']);
                }

                foreach ($tax_arr as $k => $tax_it) {
                    if (!isset($tax_rate_arr[$k])) {
                        $tax_rate_arr[$k] = $this->tax_rate_by_id($tax_it);
                    }

                    if (!in_array($tax_it, $taxes)) {
                        $taxes[$tax_it] = $tax_it;
                        $t_rate[$tax_it] = $tax_rate_arr[$k];
                        $tax_name[$tax_it] = $this->get_tax_name($tax_it) . ' (' . $tax_rate_arr[$k] . '%)';
                    }
                }
            }
        }

        if (count($tax_name) > 0) {
            foreach ($tax_name as $key => $tn) {
                $tax_val[$key] = 0;
                foreach ($details as $row_dt) {
                    if (!(strpos($row_dt['tax'] ?? '', $taxes[$key]) === false)) {
                        $tax_val[$key] += ($row_dt['into_money'] * $t_rate[$key] / 100);
                    }
                }
                $pdf_html .= '<tr id="subtotal"><td width="33%"></td><td>' . $tn . '</td><td>' . app_format_money($tax_val[$key], $base_currency->symbol) . '</td></tr>';
                $preview_html .= '<tr id="subtotal"><td>' . $tn . '</td><td>' . app_format_money($tax_val[$key], $base_currency->symbol) . '</td><tr>';
                $html .= '<tr class="tax-area_pr"><td>' . $tn . '</td><td width="65%">' . app_format_money($tax_val[$key], '') . ' ' . ($base_currency->symbol) . '</td></tr>';
                $tax_val_rs[] = $tax_val[$key];
            }
        }

        $rs['pdf_html'] = $pdf_html;
        $rs['preview_html'] = $preview_html;
        $rs['html'] = $html;
        $rs['taxes'] = $taxes;
        $rs['taxes_val'] = $tax_val_rs;
        return $rs;
    }

    public function pur_get_tax_rate($taxname)
    {
        $tax_rate = 0;
        $tax_rate_str = '';
        $tax_id_str = '';
        $tax_name_str = '';
        //var_dump($taxname); die;
        if (is_array($taxname)) {
            foreach ($taxname as $key => $value) {
                $_tax = explode("|", $value);
                if (isset($_tax[1])) {
                    $tax_rate += (float)$_tax[1];
                    if (strlen($tax_rate_str) > 0) {
                        $tax_rate_str .= '|' . $_tax[1];
                    } else {
                        $tax_rate_str .= $_tax[1];
                    }

                    $this->db->where('name', $_tax[0]);
                    $taxes = $this->db->get(db_prefix() . 'taxes')->row();
                    if ($taxes) {
                        if (strlen($tax_id_str) > 0) {
                            $tax_id_str .= '|' . $taxes->id;
                        } else {
                            $tax_id_str .= $taxes->id;
                        }
                    }

                    if (strlen($tax_name_str) > 0) {
                        $tax_name_str .= '|' . $_tax[0];
                    } else {
                        $tax_name_str .= $_tax[0];
                    }
                }
            }
        }
        return ['tax_rate' => $tax_rate, 'tax_rate_str' => $tax_rate_str, 'tax_id_str' => $tax_id_str, 'tax_name_str' => $tax_name_str];
    }

    public function add_commodity_one_item($data)
    {
        /*add data tblitem*/
        $data['rate'] = $data['rate'];
        $data['purchase_price'] = $data['purchase_price'];
        $data['can_be_purchased'] = 'can_be_purchased';
        $data['can_be_sold'] = null;
        $data['can_be_manufacturing'] = null;
        $data['can_be_inventory'] = null;

        /*create sku code*/
        if ($data['sku_code'] != '') {
            $data['sku_code'] = $data['sku_code'];
        } else {
            $data['sku_code'] = $this->create_sku_code('', '');
        }

        //update column unit name use sales/items
        $unit_type = get_unit_type_item($data['unit_id']);
        if ($unit_type && !is_array($unit_type)) {
            $data['unit'] = $unit_type->unit_name;
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            unset($data['custom_fields']);
        }

        $this->db->insert(db_prefix() . 'items', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            if (isset($custom_fields)) {
                handle_custom_fields_post($insert_id, $custom_fields, true);
            }

            return $insert_id;
        }

        /*add data tblinventory*/
        return false;
    }

    public function  create_sku_code($commodity_group, $sub_group)
    {
        // input  commodity group, sub group
        //get commodity group from id
        $group_character = '';
        if (isset($commodity_group)) {

            $sql_group_where = 'SELECT * FROM ' . db_prefix() . 'items_groups where id = "' . $commodity_group . '"';
            $group_value = $this->db->query($sql_group_where)->row();
            if ($group_value) {

                if ($group_value->commodity_group_code != '') {
                    $group_character = mb_substr($group_value->commodity_group_code, 0, 1, "UTF-8") . '-';
                }
            }
        }

        //get sku code from sku id
        $sub_code = '';




        $sql_where = 'SELECT * FROM ' . db_prefix() . 'items order by id desc limit 1';
        $last_commodity_id = $this->db->query($sql_where)->row();
        if ($last_commodity_id) {
            $next_commodity_id = (int)$last_commodity_id->id + 1;
        } else {
            $next_commodity_id = 1;
        }
        $commodity_id_length = strlen((string)$next_commodity_id);

        $commodity_str_betwen = '';

        $create_candidate_code = '';

        switch ($commodity_id_length) {
            case 1:
                $commodity_str_betwen = '000';
                break;
            case 2:
                $commodity_str_betwen = '00';
                break;
            case 3:
                $commodity_str_betwen = '0';
                break;

            default:
                $commodity_str_betwen = '0';
                break;
        }


        return  $group_character . $sub_code . $commodity_str_betwen . $next_commodity_id; // X_X_000.id auto increment


    }

    public function generate_commodity_barcode()
    {
        $item = false;
        do {
            $length = 11;
            $chars = '0123456789';
            $count = mb_strlen($chars);
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $index = rand(0, $count - 1);
                $password .= mb_substr($chars, $index, 1);
            }
            $this->db->where('commodity_barcode', $password);
            $item = $this->db->get(db_prefix() . 'items')->row();
        } while ($item);

        return $password;
    }

    

    /**
     * Simplified function to send non complicated email templates for project contacts
     * @param mixed $id project id
     * @return boolean
     */
    public function send_project_customer_email($id, $template)
    {
        $sent = false;
        $contacts = $this->clients_model->get_contacts_for_project_notifications($id, 'project_emails');

        foreach ($contacts as $contact) {
            if (send_mail_template($template, $id, $contact['userid'], $contact)) {
                $sent = true;
            }
        }

        if ($sent) {
            hooks()->do_action('after_project_customer_email_sent', [
                'project_id' => $id,
                'email_template' => $template,
            ]);
        }

        return $sent;
    }

    public function mark_as($data)
    {
        $this->db->select('status');
        $this->db->where('id', $data['project_id']);
        $old_status = $this->db->get(db_prefix() . 'projects')->row()->status;

        $this->db->where('id', $data['project_id']);
        $this->db->update(db_prefix() . 'projects', [
            'status' => $data['status_id'],
        ]);
        if ($this->db->affected_rows() > 0) {
            hooks()->do_action('project_status_changed', [
                'status' => $data['status_id'],
                'project_id' => $data['project_id'],
            ]);


            if ($data['status_id'] == 4) {
                $this->log_activity($data['project_id'], 'project_marked_as_finished');
                $this->db->where('id', $data['project_id']);
                $this->db->update(db_prefix() . 'projects', ['date_finished' => date('Y-m-d H:i:s')]);
            } else {
                $this->log_activity($data['project_id'], 'project_status_updated', '<b><lang>project_status_' . $data['status_id'] . '</lang></b>');
                if ($old_status == 4) {
                    $this->db->where('id', $data['project_id']);
                    $this->db->update(db_prefix() . 'projects', ['date_finished' => null]);
                }
            }

            if ($data['notify_project_members_status_change'] == 1) {
                $this->_notify_project_members_status_change($data['project_id'], $old_status, $data['status_id']);
            }

            if ($data['mark_all_tasks_as_completed'] == 1) {
                $this->_mark_all_project_tasks_as_completed($data['project_id']);
            }

            if (isset($data['cancel_recurring_tasks']) && $data['cancel_recurring_tasks'] == 'true') {
                $this->cancel_recurring_tasks($data['project_id']);
            }

            if (
                isset($data['send_project_marked_as_finished_email_to_contacts'])
                && $data['send_project_marked_as_finished_email_to_contacts'] == 1
            ) {
                $this->send_project_customer_email($data['project_id'], 'project_marked_as_finished_to_customer');
            }

            return true;
        }


        return false;
    }

    private function _notify_project_members_status_change($id, $old_status, $new_status)
    {
        $members = $this->get_project_members($id);
        $notifiedUsers = [];
        foreach ($members as $member) {
            if ($member['staff_id'] != get_staff_user_id()) {
                $notified = add_notification([
                    'fromuserid' => get_staff_user_id(),
                    'description' => 'not_project_status_updated',
                    'link' => 'projects/view/' . $id,
                    'touserid' => $member['staff_id'],
                    'additional_data' => serialize([
                        '<lang>project_status_' . $old_status . '</lang>',
                        '<lang>project_status_' . $new_status . '</lang>',
                    ]),
                ]);
                if ($notified) {
                    array_push($notifiedUsers, $member['staff_id']);
                }
            }
        }
        pusher_trigger_notification($notifiedUsers);
    }

    private function _mark_all_project_tasks_as_completed($id)
    {
        $this->db->where('rel_type', 'project');
        $this->db->where('rel_id', $id);
        $this->db->update(db_prefix() . 'tasks', [
            'status' => 5,
            'datefinished' => date('Y-m-d H:i:s'),
        ]);
        $tasks = $this->get_tasks($id);
        foreach ($tasks as $task) {
            $this->db->where('task_id', $task['id']);
            $this->db->where('end_time IS NULL');
            $this->db->update(db_prefix() . 'taskstimers', [
                'end_time' => time(),
            ]);
        }
        $this->log_activity($id, 'project_activity_marked_all_tasks_as_complete');
    }

    public function add_edit_members($data, $id)
    {
        $affectedRows = 0;
        if (isset($data['project_members'])) {
            $project_members = $data['project_members'];
        }

        $new_project_members_to_receive_email = [];
        $this->db->select('name,clientid');
        $this->db->where('id', $id);
        $project = $this->db->get(db_prefix() . 'projects')->row();
        $project_name = $project->name;
        $client_id = $project->clientid;

        $project_members_in = $this->get_project_members($id);
        if (sizeof($project_members_in) > 0) {
            foreach ($project_members_in as $project_member) {
                if (isset($project_members)) {
                    if (!in_array($project_member['staff_id'], $project_members)) {
                        $this->db->where('project_id', $id);
                        $this->db->where('staff_id', $project_member['staff_id']);
                        $this->db->delete(db_prefix() . 'project_members');
                        if ($this->db->affected_rows() > 0) {
                            $this->db->where('staff_id', $project_member['staff_id']);
                            $this->db->where('project_id', $id);
                            $this->db->delete(db_prefix() . 'pinned_projects');

                            $this->log_activity($id, 'project_activity_removed_team_member', get_staff_full_name($project_member['staff_id']));
                            $affectedRows++;
                        }
                    }
                } else {
                    $this->db->where('project_id', $id);
                    $this->db->delete(db_prefix() . 'project_members');
                    if ($this->db->affected_rows() > 0) {
                        $affectedRows++;
                    }
                }
            }
            if (isset($project_members)) {
                $notifiedUsers = [];
                foreach ($project_members as $staff_id) {
                    $this->db->where('project_id', $id);
                    $this->db->where('staff_id', $staff_id);
                    $_exists = $this->db->get(db_prefix() . 'project_members')->row();
                    if (!$_exists) {
                        if (empty($staff_id)) {
                            continue;
                        }
                        $this->db->insert(db_prefix() . 'project_members', [
                            'project_id' => $id,
                            'staff_id' => $staff_id,
                        ]);
                        if ($this->db->affected_rows() > 0) {
                            if ($staff_id != get_staff_user_id()) {
                                $notified = add_notification([
                                    'fromuserid' => get_staff_user_id(),
                                    'description' => 'not_staff_added_as_project_member',
                                    'link' => 'projects/view/' . $id,
                                    'touserid' => $staff_id,
                                    'additional_data' => serialize([
                                        $project_name,
                                    ]),
                                ]);
                                array_push($new_project_members_to_receive_email, $staff_id);
                                if ($notified) {
                                    array_push($notifiedUsers, $staff_id);
                                }
                            }


                            $this->log_activity($id, 'project_activity_added_team_member', get_staff_full_name($staff_id));
                            $affectedRows++;
                        }
                    }
                }
                pusher_trigger_notification($notifiedUsers);
            }
        } else {
            if (isset($project_members)) {
                $notifiedUsers = [];
                foreach ($project_members as $staff_id) {
                    if (empty($staff_id)) {
                        continue;
                    }
                    $this->db->insert(db_prefix() . 'project_members', [
                        'project_id' => $id,
                        'staff_id' => $staff_id,
                    ]);
                    if ($this->db->affected_rows() > 0) {
                        if ($staff_id != get_staff_user_id()) {
                            $notified = add_notification([
                                'fromuserid' => get_staff_user_id(),
                                'description' => 'not_staff_added_as_project_member',
                                'link' => 'projects/view/' . $id,
                                'touserid' => $staff_id,
                                'additional_data' => serialize([
                                    $project_name,
                                ]),
                            ]);
                            array_push($new_project_members_to_receive_email, $staff_id);
                            if ($notifiedUsers) {
                                array_push($notifiedUsers, $staff_id);
                            }
                        }
                        $this->log_activity($id, 'project_activity_added_team_member', get_staff_full_name($staff_id));
                        $affectedRows++;
                    }
                }
                pusher_trigger_notification($notifiedUsers);
            }
        }

        if (count($new_project_members_to_receive_email) > 0) {
            $all_members = $this->get_project_members($id);
            foreach ($all_members as $data) {
                if (in_array($data['staff_id'], $new_project_members_to_receive_email)) {
                    send_mail_template('project_staff_added_as_member', $data, $id, $client_id);
                }
            }

            hooks()->do_action('after_project_staff_added_as_member', [
                'project_id' => $id,
                'new_project_members_to_receive_email' => $new_project_members_to_receive_email,
            ]);
        }
        if ($affectedRows > 0) {
            return true;
        }

        return false;
    }

    public function is_member($project_id, $staff_id = '')
    {
        if (!is_numeric($staff_id)) {
            $staff_id = get_staff_user_id();
        }
        $member = total_rows(db_prefix() . 'project_members', [
            'staff_id' => $staff_id,
            'project_id' => $project_id,
        ]);
        if ($member > 0) {
            return true;
        }

        return false;
    }

    public function get_projects_for_ticket($client_id)
    {
        return $this->get('', [
            'clientid' => $client_id,
        ]);
    }

    public function get_project_settings($project_id)
    {
        $this->db->where('project_id', $project_id);

        return $this->db->get(db_prefix() . 'project_settings')->result_array();
    }

    public function get_project_members($id, $with_name = false)
    {
        if ($with_name) {
            $this->db->select('firstname,lastname,email,project_id,staff_id');
        } else {
            $this->db->select('email,project_id,staff_id');
        }
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid=' . db_prefix() . 'project_members.staff_id');
        $this->db->where('project_id', $id);

        return $this->db->get(db_prefix() . 'project_members')->result_array();
    }

    public function remove_team_member($project_id, $staff_id)
    {
        $this->db->where('project_id', $project_id);
        $this->db->where('staff_id', $staff_id);
        $this->db->delete(db_prefix() . 'project_members');
        if ($this->db->affected_rows() > 0) {

            // Remove member from tasks where is assigned
            $this->db->where('staffid', $staff_id);
            $this->db->where('taskid IN (SELECT id FROM ' . db_prefix() . 'tasks WHERE rel_type="project" AND rel_id="' . $this->db->escape_str($project_id) . '")');
            $this->db->delete(db_prefix() . 'task_assigned');

            $this->log_activity($project_id, 'project_activity_removed_team_member', get_staff_full_name($staff_id));

            return true;
        }

        return false;
    }

    public function get_timesheets($project_id, $tasks_ids = [])
    {
        if (count($tasks_ids) == 0) {
            $tasks = $this->get_tasks($project_id);
            $tasks_ids = [];
            foreach ($tasks as $task) {
                array_push($tasks_ids, $task['id']);
            }
        }
        if (count($tasks_ids) > 0) {
            $this->db->where('task_id IN(' . implode(', ', $tasks_ids) . ')');
            $timesheets = $this->db->get(db_prefix() . 'taskstimers')->result_array();
            $i = 0;
            foreach ($timesheets as $t) {
                $task = $this->tasks_model->get($t['task_id']);
                $timesheets[$i]['task_data'] = $task;
                $timesheets[$i]['staff_name'] = get_staff_full_name($t['staff_id']);
                if (!is_null($t['end_time'])) {
                    $timesheets[$i]['total_spent'] = $t['end_time'] - $t['start_time'];
                } else {
                    $timesheets[$i]['total_spent'] = time() - $t['start_time'];
                }
                $i++;
            }

            return $timesheets;
        }

        return [];
    }

    public function get_discussion($id, $project_id = '')
    {
        if ($project_id != '') {
            $this->db->where('project_id', $project_id);
        }
        $this->db->where('id', $id);
        if (is_client_logged_in()) {
            $this->db->where('show_to_customer', 1);
            $this->db->where('project_id IN (SELECT id FROM ' . db_prefix() . 'projects WHERE clientid=' . get_client_user_id() . ')');
        }
        $discussion = $this->db->get(db_prefix() . 'projectdiscussions')->row();
        if ($discussion) {
            return $discussion;
        }

        return false;
    }

    public function get_discussion_comment($id)
    {
        $this->db->where('id', $id);
        $comment = $this->db->get(db_prefix() . 'projectdiscussioncomments')->row();
        if ($comment->contact_id != 0) {
            if (is_client_logged_in()) {
                if ($comment->contact_id == get_contact_user_id()) {
                    $comment->created_by_current_user = true;
                } else {
                    $comment->created_by_current_user = false;
                }
            } else {
                $comment->created_by_current_user = false;
            }
            $comment->profile_picture_url = contact_profile_image_url($comment->contact_id);
        } else {
            if (is_client_logged_in()) {
                $comment->created_by_current_user = false;
            } else {
                if (is_staff_logged_in()) {
                    if ($comment->staff_id == get_staff_user_id()) {
                        $comment->created_by_current_user = true;
                    } else {
                        $comment->created_by_current_user = false;
                    }
                } else {
                    $comment->created_by_current_user = false;
                }
            }
            if (is_admin($comment->staff_id)) {
                $comment->created_by_admin = true;
            } else {
                $comment->created_by_admin = false;
            }
            $comment->profile_picture_url = staff_profile_image_url($comment->staff_id);
        }
        $comment->created = (strtotime($comment->created) * 1000);
        if (!empty($comment->modified)) {
            $comment->modified = (strtotime($comment->modified) * 1000);
        }
        if (!is_null($comment->file_name)) {
            $comment->file_url = site_url('uploads/discussions/' . $comment->discussion_id . '/' . $comment->file_name);
        }

        return $comment;
    }

    public function get_discussion_comments($id, $type)
    {
        $this->db->where('discussion_id', $id);
        $this->db->where('discussion_type', $type);
        $comments = $this->db->get(db_prefix() . 'projectdiscussioncomments')->result_array();
        $i = 0;
        $allCommentsIDS = [];
        $allCommentsParentIDS = [];
        foreach ($comments as $comment) {
            $allCommentsIDS[] = $comment['id'];
            if (!empty($comment['parent'])) {
                $allCommentsParentIDS[] = $comment['parent'];
            }

            if ($comment['contact_id'] != 0) {
                if (is_client_logged_in()) {
                    if ($comment['contact_id'] == get_contact_user_id()) {
                        $comments[$i]['created_by_current_user'] = true;
                    } else {
                        $comments[$i]['created_by_current_user'] = false;
                    }
                } else {
                    $comments[$i]['created_by_current_user'] = false;
                }
                $comments[$i]['profile_picture_url'] = contact_profile_image_url($comment['contact_id']);
            } else {
                if (is_client_logged_in()) {
                    $comments[$i]['created_by_current_user'] = false;
                } else {
                    if (is_staff_logged_in()) {
                        if ($comment['staff_id'] == get_staff_user_id()) {
                            $comments[$i]['created_by_current_user'] = true;
                        } else {
                            $comments[$i]['created_by_current_user'] = false;
                        }
                    } else {
                        $comments[$i]['created_by_current_user'] = false;
                    }
                }
                if (is_admin($comment['staff_id'])) {
                    $comments[$i]['created_by_admin'] = true;
                } else {
                    $comments[$i]['created_by_admin'] = false;
                }
                $comments[$i]['profile_picture_url'] = staff_profile_image_url($comment['staff_id']);
            }
            if (!is_null($comment['file_name'])) {
                $comments[$i]['file_url'] = site_url('uploads/discussions/' . $id . '/' . $comment['file_name']);
            }
            $comments[$i]['created'] = (strtotime($comment['created']) * 1000);
            if (!empty($comment['modified'])) {
                $comments[$i]['modified'] = (strtotime($comment['modified']) * 1000);
            }
            $i++;
        }

        // Ticket #5471
        foreach ($allCommentsParentIDS as $parent_id) {
            if (!in_array($parent_id, $allCommentsIDS)) {
                foreach ($comments as $key => $comment) {
                    if ($comment['parent'] == $parent_id) {
                        $comments[$key]['parent'] = null;
                    }
                }
            }
        }

        return $comments;
    }

    public function get_discussions($project_id)
    {
        $this->db->where('project_id', $project_id);
        if (is_client_logged_in()) {
            $this->db->where('show_to_customer', 1);
        }
        $discussions = $this->db->get(db_prefix() . 'projectdiscussions')->result_array();
        $i = 0;
        foreach ($discussions as $discussion) {
            $discussions[$i]['total_comments'] = total_rows(db_prefix() . 'projectdiscussioncomments', [
                'discussion_id' => $discussion['id'],
                'discussion_type' => 'regular',
            ]);
            $i++;
        }

        return $discussions;
    }

    public function add_discussion_comment($data, $discussion_id, $type)
    {
        $discussion = $this->get_discussion($discussion_id);
        $_data['discussion_id'] = $discussion_id;
        $_data['discussion_type'] = $type;
        if (isset($data['content'])) {
            $_data['content'] = $data['content'];
        }
        if (isset($data['parent']) && $data['parent'] != null) {
            $_data['parent'] = $data['parent'];
        }
        if (is_client_logged_in()) {
            $_data['contact_id'] = get_contact_user_id();
            $_data['fullname'] = get_contact_full_name($_data['contact_id']);
            $_data['staff_id'] = 0;
        } else {
            $_data['contact_id'] = 0;
            $_data['staff_id'] = get_staff_user_id();
            $_data['fullname'] = get_staff_full_name($_data['staff_id']);
        }
        $_data = handle_project_discussion_comment_attachments($discussion_id, $data, $_data);

        $_data['created'] = date('Y-m-d H:i:s');

        $_data = hooks()->apply_filters('before_add_project_discussion_comment', $_data, $discussion_id);

        $this->db->insert(db_prefix() . 'projectdiscussioncomments', $_data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            if ($type == 'regular') {
                $discussion = $this->get_discussion($discussion_id);
                $not_link = 'projects/view/' . $discussion->project_id . '?group=project_discussions&discussion_id=' . $discussion_id;
            } else {
                $discussion = $this->get_file($discussion_id);
                $not_link = 'projects/view/' . $discussion->project_id . '?group=project_files&file_id=' . $discussion_id;
                $discussion->show_to_customer = $discussion->visible_to_customer;
            }

            $emailTemplateData = [
                'staff' => [
                    'discussion_id' => $discussion_id,
                    'discussion_comment_id' => $insert_id,
                    'discussion_type' => $type,
                ],
                'customers' => [
                    'customer_template' => true,
                    'discussion_id' => $discussion_id,
                    'discussion_comment_id' => $insert_id,
                    'discussion_type' => $type,
                ],
            ];

            if (isset($_data['file_name'])) {
                $emailTemplateData['attachments'] = [
                    [
                        'attachment' => PROJECT_DISCUSSION_ATTACHMENT_FOLDER . $discussion_id . '/' . $_data['file_name'],
                        'filename' => $_data['file_name'],
                        'type' => $_data['file_mime_type'],
                        'read' => true,
                    ],
                ];
            }

            $notification_data = [
                'description' => 'not_commented_on_project_discussion',
                'link' => $not_link,
            ];

            if (is_client_logged_in()) {
                $notification_data['fromclientid'] = get_contact_user_id();
            } else {
                $notification_data['fromuserid'] = get_staff_user_id();
            }
            $notifiedUsers = [];

            $regex = "/data\-mention\-id\=\"(\d+)\"/";
            if (isset($data['content']) && preg_match_all($regex, $data['content'], $mentionedStaff, PREG_PATTERN_ORDER)) {
                $members = array_unique($mentionedStaff[1], SORT_NUMERIC);
                $this->send_project_email_mentioned_users($discussion->project_id, 'project_new_discussion_comment_to_staff', $members, $emailTemplateData);

                foreach ($members as $memberId) {
                    if ($memberId == get_staff_user_id() && !is_client_logged_in()) {
                        continue;
                    }

                    $notification_data['touserid'] = $memberId;
                    if (add_notification($notification_data)) {
                        array_push($notifiedUsers, $memberId);
                    }
                }
            } else {
                $this->send_project_email_template($discussion->project_id, 'project_new_discussion_comment_to_staff', 'project_new_discussion_comment_to_customer', $discussion->show_to_customer, $emailTemplateData);

                $members = $this->get_project_members($discussion->project_id);
                foreach ($members as $member) {
                    if ($member['staff_id'] == get_staff_user_id() && !is_client_logged_in()) {
                        continue;
                    }
                    $notification_data['touserid'] = $member['staff_id'];
                    if (add_notification($notification_data)) {
                        array_push($notifiedUsers, $member['staff_id']);
                    }
                }
            }

            $this->log_activity($discussion->project_id, 'project_activity_commented_on_discussion', $discussion->subject, $discussion->show_to_customer);

            pusher_trigger_notification($notifiedUsers);

            $this->_update_discussion_last_activity($discussion_id, $type);

            hooks()->do_action('after_add_discussion_comment', $insert_id);

            return $this->get_discussion_comment($insert_id);
        }

        return false;
    }

    public function update_discussion_comment($data)
    {
        $comment = $this->get_discussion_comment($data['id']);
        $this->db->where('id', $data['id']);
        $this->db->update(db_prefix() . 'projectdiscussioncomments', [
            'modified' => date('Y-m-d H:i:s'),
            'content' => $data['content'],
        ]);
        if ($this->db->affected_rows() > 0) {
            $this->_update_discussion_last_activity($comment->discussion_id, $comment->discussion_type);
        }

        return $this->get_discussion_comment($data['id']);
    }

    public function delete_discussion_comment($id, $logActivity = true)
    {
        $comment = $this->get_discussion_comment($id);
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'projectdiscussioncomments');
        if ($this->db->affected_rows() > 0) {
            $this->delete_discussion_comment_attachment($comment->file_name, $comment->discussion_id);
            if ($logActivity) {
                $additional_data = '';
                if ($comment->discussion_type == 'regular') {
                    $discussion = $this->get_discussion($comment->discussion_id);
                    $not = 'project_activity_deleted_discussion_comment';
                    $additional_data .= $discussion->subject . '<br />' . $comment->content;
                } else {
                    $discussion = $this->get_file($comment->discussion_id);
                    $not = 'project_activity_deleted_file_discussion_comment';
                    $additional_data .= $discussion->subject . '<br />' . $comment->content;
                }

                if (!is_null($comment->file_name)) {
                    $additional_data .= $comment->file_name;
                }

                $this->log_activity($discussion->project_id, $not, $additional_data);
            }
        }

        $this->db->where('parent', $id);
        $this->db->update(db_prefix() . 'projectdiscussioncomments', [
            'parent' => null,
        ]);

        if ($this->db->affected_rows() > 0 && $logActivity) {
            $this->_update_discussion_last_activity($comment->discussion_id, $comment->discussion_type);
        }

        return true;
    }

    public function delete_discussion_comment_attachment($file_name, $discussion_id)
    {
        $path = PROJECT_DISCUSSION_ATTACHMENT_FOLDER . $discussion_id;
        if (!is_null($file_name)) {
            if (file_exists($path . '/' . $file_name)) {
                unlink($path . '/' . $file_name);
            }
        }
        if (is_dir($path)) {
            // Check if no attachments left, so we can delete the folder also
            $other_attachments = list_files($path);
            if (count($other_attachments) == 0) {
                delete_dir($path);
            }
        }
    }

    public function add_discussion($data)
    {
        if (is_client_logged_in()) {
            $data['contact_id'] = get_contact_user_id();
            $data['staff_id'] = 0;
            $data['show_to_customer'] = 1;
        } else {
            $data['staff_id'] = get_staff_user_id();
            $data['contact_id'] = 0;
            if (isset($data['show_to_customer'])) {
                $data['show_to_customer'] = 1;
            } else {
                $data['show_to_customer'] = 0;
            }
        }
        $data['datecreated'] = date('Y-m-d H:i:s');
        $data['description'] = nl2br($data['description']);
        $this->db->insert(db_prefix() . 'projectdiscussions', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            $members = $this->get_project_members($data['project_id']);
            $notification_data = [
                'description' => 'not_created_new_project_discussion',
                'link' => 'projects/view/' . $data['project_id'] . '?group=project_discussions&discussion_id=' . $insert_id,
            ];

            if (is_client_logged_in()) {
                $notification_data['fromclientid'] = get_contact_user_id();
            } else {
                $notification_data['fromuserid'] = get_staff_user_id();
            }

            $notifiedUsers = [];
            foreach ($members as $member) {
                if ($member['staff_id'] == get_staff_user_id() && !is_client_logged_in()) {
                    continue;
                }
                $notification_data['touserid'] = $member['staff_id'];
                if (add_notification($notification_data)) {
                    array_push($notifiedUsers, $member['staff_id']);
                }
            }
            pusher_trigger_notification($notifiedUsers);
            $this->send_project_email_template($data['project_id'], 'project_discussion_created_to_staff', 'project_discussion_created_to_customer', $data['show_to_customer'], [
                'staff' => [
                    'discussion_id' => $insert_id,
                    'discussion_type' => 'regular',
                ],
                'customers' => [
                    'customer_template' => true,
                    'discussion_id' => $insert_id,
                    'discussion_type' => 'regular',
                ],
            ]);
            $this->log_activity($data['project_id'], 'project_activity_created_discussion', $data['subject'], $data['show_to_customer']);

            return $insert_id;
        }

        return false;
    }

    public function edit_discussion($data, $id)
    {
        $this->db->where('id', $id);
        if (isset($data['show_to_customer'])) {
            $data['show_to_customer'] = 1;
        } else {
            $data['show_to_customer'] = 0;
        }
        $data['description'] = nl2br($data['description']);
        $this->db->update(db_prefix() . 'projectdiscussions', $data);
        if ($this->db->affected_rows() > 0) {
            $this->log_activity($data['project_id'], 'project_activity_updated_discussion', $data['subject'], $data['show_to_customer']);

            return true;
        }

        return false;
    }

    public function delete_discussion($id, $logActivity = true)
    {
        $discussion = $this->get_discussion($id);
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'projectdiscussions');
        if ($this->db->affected_rows() > 0) {
            if ($logActivity) {
                $this->log_activity($discussion->project_id, 'project_activity_deleted_discussion', $discussion->subject, $discussion->show_to_customer);
            }
            $this->_delete_discussion_comments($id, 'regular');

            return true;
        }

        return false;
    }

    public function copy($project_id, $data)
    {
        $_new_data = [];
        $project = $this->get($project_id);
        $settings = $this->get_project_settings($project_id);
        $fields = $this->db->list_fields(db_prefix() . 'projects');

        foreach ($fields as $field) {
            if (isset($project->$field)) {
                $_new_data[$field] = $project->$field;
            }
        }

        unset($_new_data['id']);
        $_new_data['clientid'] = $data['clientid_copy_project'];
        unset($_new_data['clientid_copy_project']);

        $_new_data['start_date'] = to_sql_date($data['start_date']);

        if ($_new_data['start_date'] > date('Y-m-d')) {
            $_new_data['status'] = 1;
        } else {
            $_new_data['status'] = 2;
        }
        if ($data['deadline']) {
            $_new_data['deadline'] = to_sql_date($data['deadline']);
        } else {
            $_new_data['deadline'] = null;
        }

        if ($data['name']) {
            $_new_data['name'] = $data['name'];
        }

        $_new_data['project_created'] = date('Y-m-d H:i:s');
        $_new_data['addedfrom'] = get_staff_user_id();

        $_new_data['date_finished'] = null;

        if ($project->contact_notification == 2) {
            $contacts = $this->clients_model->get_contacts($_new_data['clientid'], ['active' => 1, 'project_emails' => 1]);
            $_new_data['notify_contacts'] = serialize(array_column($contacts, 'id'));
        }

        $this->db->insert(db_prefix() . 'projects', $_new_data);
        $id = $this->db->insert_id();
        if ($id) {
            $tags = get_tags_in($project_id, 'project');
            handle_tags_save($tags, $id, 'project');

            foreach ($settings as $setting) {
                $this->db->insert(db_prefix() . 'project_settings', [
                    'project_id' => $id,
                    'name' => $setting['name'],
                    'value' => $setting['value'],
                ]);
            }

            $added_tasks = [];
            $tasks = $this->get_tasks($project_id);

            if (isset($data['tasks'])) {
                foreach ($tasks as $task) {
                    if (isset($data['task_include_followers'])) {
                        $copy_task_data['copy_task_followers'] = 'true';
                    }

                    if (isset($data['task_include_assignees'])) {
                        $copy_task_data['copy_task_assignees'] = 'true';
                    }

                    if (isset($data['tasks_include_checklist_items'])) {
                        $copy_task_data['copy_task_checklist_items'] = 'true';
                    }

                    $copy_task_data['copy_from'] = $task['id'];

                    // For new task start date, we will find the difference in days between
                    // the old project start and and the old task start date and then
                    // based on the new project start date, we will add e.q. 15 days to be
                    // new task start date to the task
                    // e.q. old project start date 2020-04-01, old task start date 2020-04-15 and due date 2020-04-30
                    // copy project and set start date 2020-06-01
                    // new task start date will be 2020-06-15 and below due date 2020-06-30
                    $dStart = new DateTime($project->start_date);
                    $dEnd = new DateTime($task['startdate']);
                    $dDiff = $dStart->diff($dEnd);
                    $startDate = new DateTime($_new_data['start_date']);
                    $startDate->modify('+' . $dDiff->days . ' DAY');
                    $newTaskStartDate = $startDate->format('Y-m-d');

                    $merge = [
                        'rel_id' => $id,
                        'rel_type' => 'project',
                        'last_recurring_date' => null,
                        'startdate' => $newTaskStartDate,
                        'status' => $data['copy_project_task_status'],
                    ];

                    // Calculate the diff in days between the task start and due date
                    // then add these days to the new task start date to be used as this task due date
                    if ($task['duedate']) {
                        $dStart = new DateTime($task['startdate']);
                        $dEnd = new DateTime($task['duedate']);
                        $dDiff = $dStart->diff($dEnd);
                        $dueDate = new DateTime($newTaskStartDate);
                        $dueDate->modify('+' . $dDiff->days . ' DAY');
                        $merge['duedate'] = $dueDate->format('Y-m-d');
                    }

                    $task_id = $this->tasks_model->copy($copy_task_data, $merge);

                    if ($task_id) {
                        array_push($added_tasks, $task_id);
                    }
                }
            }

            if (isset($data['milestones'])) {
                $milestones = $this->get_milestones($project_id);
                $_added_milestones = [];
                foreach ($milestones as $milestone) {
                    $newProjectStartDate = new DateTimeImmutable($_new_data['start_date']);
                    $oldProjectStartDate = new DateTime($project->start_date);
                    $oldMilestoneStartDate = new DateTime($milestone['start_date']); // assuming that the MySQL column added is start_date
                    $diffBetweenOldProjectStartDateAndOldMilesoneStartDate = $oldProjectStartDate->diff($oldMilestoneStartDate);
                    $newMilestoneStartDate = $newProjectStartDate->modify('+' . $diffBetweenOldProjectStartDateAndOldMilesoneStartDate->days . ' DAY');

                    $oldMilestoneDueDate = new DateTime($milestone['due_date']);
                    $diffBetweenOldMilestoneDueDateAndOldMilesoneStartDate = $oldMilestoneStartDate->diff($oldMilestoneDueDate);
                    $newMilestoneDueDate = $newMilestoneStartDate->modify('+' . $diffBetweenOldMilestoneDueDateAndOldMilesoneStartDate->days . ' DAY');


                    $this->db->insert(db_prefix() . 'milestones', [
                        'name' => $milestone['name'],
                        'project_id' => $id,
                        'milestone_order' => $milestone['milestone_order'],
                        'description_visible_to_customer' => $milestone['description_visible_to_customer'],
                        'description' => $milestone['description'],
                        'start_date' => $newMilestoneStartDate->format('Y-m-d'),
                        'due_date' => $newMilestoneDueDate->format('Y-m-d'),
                        'datecreated' => date('Y-m-d'),
                        'color' => $milestone['color'],
                        'hide_from_customer' => $milestone['hide_from_customer'],
                    ]);

                    $milestone_id = $this->db->insert_id();

                    if ($milestone_id) {
                        $_added_milestone_data = [];
                        $_added_milestone_data['id'] = $milestone_id;
                        $_added_milestone_data['name'] = $milestone['name'];
                        $_added_milestones[] = $_added_milestone_data;
                    }
                }

                if (isset($data['tasks'])) {
                    if (count($added_tasks) > 0) {
                        // Original project tasks
                        foreach ($tasks as $task) {
                            if ($task['milestone'] != 0) {
                                $this->db->where('id', $task['milestone']);
                                $milestone = $this->db->get(db_prefix() . 'milestones')->row();

                                if ($milestone) {
                                    foreach ($_added_milestones as $added_milestone) {
                                        if ($milestone->name == $added_milestone['name']) {
                                            $this->db->where('id IN (' . implode(', ', $added_tasks) . ')');
                                            $this->db->where('milestone', $task['milestone']);
                                            $this->db->update(db_prefix() . 'tasks', [
                                                'milestone' => $added_milestone['id'],
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // milestones not set
                if (count($added_tasks)) {
                    foreach ($added_tasks as $task) {
                        $this->db->where('id', $task['id']);
                        $this->db->update(db_prefix() . 'tasks', [
                            'milestone' => 0,
                        ]);
                    }
                }
            }

            if (isset($data['members'])) {
                $members = $this->get_project_members($project_id);
                $_members = [];

                foreach ($members as $member) {
                    array_push($_members, $member['staff_id']);
                }

                $this->add_edit_members([
                    'project_members' => $_members,
                ], $id);
            }

            foreach (get_custom_fields('projects') as $field) {
                $value = get_custom_field_value($project_id, $field['id'], 'projects', false);
                if ($value != '') {
                    $this->db->insert(db_prefix() . 'customfieldsvalues', [
                        'relid' => $id,
                        'fieldid' => $field['id'],
                        'fieldto' => 'projects',
                        'value' => $value,
                    ]);
                }
            }

            $this->log_activity($id, 'project_activity_created');

            log_activity('Project Copied [ID: ' . $project_id . ', NewID: ' . $id . ']');

            hooks()->do_action('project_copied', [
                'project_id' => $project_id,
                'new_project_id' => $id,
            ]);

            return $id;
        }

        return false;
    }

    public function get_staff_notes($project_id)
    {
        $this->db->where('project_id', $project_id);
        $this->db->where('staff_id', get_staff_user_id());
        $notes = $this->db->get(db_prefix() . 'project_notes')->row();
        if ($notes) {
            return $notes->content;
        }

        return '';
    }

    public function save_note($data, $project_id)
    {
        // Check if the note exists for this project;
        $this->db->where('project_id', $project_id);
        $this->db->where('staff_id', get_staff_user_id());
        $notes = $this->db->get(db_prefix() . 'project_notes')->row();
        if ($notes) {
            $this->db->where('id', $notes->id);
            $this->db->update(db_prefix() . 'project_notes', [
                'content' => $data['content'],
            ]);
            if ($this->db->affected_rows() > 0) {
                return true;
            }

            return false;
        }
        $this->db->insert(db_prefix() . 'project_notes', [
            'staff_id' => get_staff_user_id(),
            'content' => $data['content'],
            'project_id' => $project_id,
        ]);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            return true;
        }

        return false;


        return false;
    }

    public function delete($project_id)
    {
        hooks()->do_action('before_project_deleted', $project_id);

        $project_name = get_project_name_by_id($project_id);

        $this->db->where('id', $project_id);
        $this->db->delete(db_prefix() . 'projects');
        if ($this->db->affected_rows() > 0) {
            $this->db->where('project_id', $project_id);
            $this->db->delete(db_prefix() . 'project_members');

            $this->db->where('project_id', $project_id);
            $this->db->delete(db_prefix() . 'project_notes');

            $this->db->where('project_id', $project_id);
            $this->db->delete(db_prefix() . 'milestones');

            // Delete the custom field values
            $this->db->where('relid', $project_id);
            $this->db->where('fieldto', 'projects');
            $this->db->delete(db_prefix() . 'customfieldsvalues');

            $this->db->where('rel_id', $project_id);
            $this->db->where('rel_type', 'project');
            $this->db->delete(db_prefix() . 'taggables');


            $this->db->where('project_id', $project_id);
            $discussions = $this->db->get(db_prefix() . 'projectdiscussions')->result_array();
            foreach ($discussions as $discussion) {
                $discussion_comments = $this->get_discussion_comments($discussion['id'], 'regular');
                foreach ($discussion_comments as $comment) {
                    $this->delete_discussion_comment_attachment($comment['file_name'], $discussion['id']);
                }
                $this->db->where('discussion_id', $discussion['id']);
                $this->db->delete(db_prefix() . 'projectdiscussioncomments');
            }
            $this->db->where('project_id', $project_id);
            $this->db->delete(db_prefix() . 'projectdiscussions');

            $files = $this->get_files($project_id);
            foreach ($files as $file) {
                $this->remove_file($file['id']);
            }

            $tasks = $this->get_tasks($project_id);
            foreach ($tasks as $task) {
                $this->tasks_model->delete_task($task['id'], false);
            }

            $this->db->where('project_id', $project_id);
            $this->db->delete(db_prefix() . 'project_settings');

            $this->db->where('project_id', $project_id);
            $this->db->delete(db_prefix() . 'project_activity');

            $this->db->where('project_id', $project_id);
            $this->db->update(db_prefix() . 'expenses', [
                'project_id' => 0,
            ]);

            $this->db->where('project_id', $project_id);
            $this->db->update(db_prefix() . 'contracts', [
                'project_id' => null,
            ]);

            $this->db->where('project_id', $project_id);
            $this->db->update(db_prefix() . 'invoices', [
                'project_id' => 0,
            ]);

            $this->db->where('project_id', $project_id);
            $this->db->update(db_prefix() . 'creditnotes', [
                'project_id' => 0,
            ]);

            $this->db->where('project_id', $project_id);
            $this->db->update(db_prefix() . 'estimates', [
                'project_id' => 0,
            ]);

            $this->db->where('project_id', $project_id);
            $this->db->update(db_prefix() . 'tickets', [
                'project_id' => 0,
            ]);

            $this->db->where('project_id', $project_id);
            $this->db->update(db_prefix() . 'proposals', [
                'project_id' => null,
            ]);

            $this->db->where('project_id', $project_id);
            $this->db->delete(db_prefix() . 'pinned_projects');

            log_activity('Project Deleted [ID: ' . $project_id . ', Name: ' . $project_name . ']');

            hooks()->do_action('after_project_deleted', $project_id);

            return true;
        }

        return false;
    }

    public function get_activity($id = '', $limit = '', $only_project_members_activity = false)
    {
        if (!is_client_logged_in()) {
            $has_permission = staff_can('view',  'projects');
            if (!$has_permission) {
                $this->db->where('project_id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')');
            }
        }
        if (is_client_logged_in()) {
            $this->db->where('visible_to_customer', 1);
        }
        if (is_numeric($id)) {
            $this->db->where('project_id', $id);
        }
        if (is_numeric($limit)) {
            $this->db->limit($limit);
        }
        $this->db->order_by('dateadded', 'desc');
        $activities = $this->db->get(db_prefix() . 'project_activity')->result_array();
        $i = 0;
        foreach ($activities as $activity) {
            $seconds = get_string_between($activity['additional_data'], '<seconds>', '</seconds>');
            $other_lang_keys = get_string_between($activity['additional_data'], '<lang>', '</lang>');
            $_additional_data = $activity['additional_data'];

            if ($seconds != '') {
                $_additional_data = str_replace('<seconds>' . $seconds . '</seconds>', seconds_to_time_format($seconds), $_additional_data);
            }

            if ($other_lang_keys != '') {
                $_additional_data = str_replace('<lang>' . $other_lang_keys . '</lang>', _l($other_lang_keys), $_additional_data);
            }

            if (strpos($_additional_data, 'project_status_') !== false) {
                $_additional_data = get_project_status_by_id(strafter($_additional_data, 'project_status_'));

                if (isset($_additional_data['name'])) {
                    $_additional_data = $_additional_data['name'];
                }
            }

            $activities[$i]['description'] = _l($activities[$i]['description_key']);
            $activities[$i]['additional_data'] = $_additional_data;
            $activities[$i]['project_name'] = get_project_name_by_id($activity['project_id']);
            unset($activities[$i]['description_key']);
            $i++;
        }

        return $activities;
    }

    public function log_activity($project_id, $description_key, $additional_data = '', $visible_to_customer = 1)
    {
        if (!DEFINED('CRON')) {
            if (is_client_logged_in()) {
                $data['contact_id'] = get_contact_user_id();
                $data['staff_id'] = 0;
                $data['fullname'] = get_contact_full_name(get_contact_user_id());
            } elseif (is_staff_logged_in()) {
                $data['contact_id'] = 0;
                $data['staff_id'] = get_staff_user_id();
                $data['fullname'] = get_staff_full_name(get_staff_user_id());
            }
        } else {
            $data['contact_id'] = 0;
            $data['staff_id'] = 0;
            $data['fullname'] = '[CRON]';
        }
        $data['description_key'] = $description_key;
        $data['additional_data'] = $additional_data;
        $data['visible_to_customer'] = $visible_to_customer;
        $data['project_id'] = $project_id;
        $data['dateadded'] = date('Y-m-d H:i:s');

        $data = hooks()->apply_filters('before_log_project_activity', $data);

        $this->db->insert(db_prefix() . 'project_activity', $data);
    }

    public function new_project_file_notification($file_id, $project_id)
    {
        $file = $this->get_file($file_id);

        $additional_data = $file->file_name;
        $this->log_activity($project_id, 'project_activity_uploaded_file', $additional_data, $file->visible_to_customer);

        $members = $this->get_project_members($project_id);
        $notification_data = [
            'description' => 'not_project_file_uploaded',
            'link' => 'projects/view/' . $project_id . '?group=project_files&file_id=' . $file_id,
        ];

        if (is_client_logged_in()) {
            $notification_data['fromclientid'] = get_contact_user_id();
        } else {
            $notification_data['fromuserid'] = get_staff_user_id();
        }

        $notifiedUsers = [];
        foreach ($members as $member) {
            if ($member['staff_id'] == get_staff_user_id() && !is_client_logged_in()) {
                continue;
            }
            $notification_data['touserid'] = $member['staff_id'];
            if (add_notification($notification_data)) {
                array_push($notifiedUsers, $member['staff_id']);
            }
        }
        pusher_trigger_notification($notifiedUsers);

        $this->send_project_email_template(
            $project_id,
            'project_file_to_staff',
            'project_file_to_customer',
            $file->visible_to_customer,
            [
                'staff' => ['discussion_id' => $file_id, 'discussion_type' => 'file'],
                'customers' => ['customer_template' => true, 'discussion_id' => $file_id, 'discussion_type' => 'file'],
            ]
        );
    }

    public function add_external_file($data)
    {
        $insert['dateadded'] = date('Y-m-d H:i:s');
        $insert['project_id'] = $data['project_id'];
        $insert['external'] = $data['external'];
        $insert['visible_to_customer'] = $data['visible_to_customer'];
        $insert['file_name'] = $data['files'][0]['name'];
        $insert['subject'] = $data['files'][0]['name'];
        $insert['external_link'] = $data['files'][0]['link'];

        $path_parts = pathinfo($data['files'][0]['name']);
        $insert['filetype'] = get_mime_by_extension('.' . $path_parts['extension']);

        if (isset($data['files'][0]['thumbnailLink'])) {
            $insert['thumbnail_link'] = $data['files'][0]['thumbnailLink'];
        }

        if (isset($data['staffid'])) {
            $insert['staffid'] = $data['staffid'];
        } elseif (isset($data['contact_id'])) {
            $insert['contact_id'] = $data['contact_id'];
        }

        $this->db->insert(db_prefix() . 'project_files', $insert);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            $this->new_project_file_notification($insert_id, $data['project_id']);

            return $insert_id;
        }

        return false;
    }

    public function send_project_email_template($project_id, $staff_template, $customer_template, $action_visible_to_customer, $additional_data = [])
    {
        if (count($additional_data) == 0) {
            $additional_data['customers'] = [];
            $additional_data['staff'] = [];
        } elseif (count($additional_data) == 1) {
            if (!isset($additional_data['staff'])) {
                $additional_data['staff'] = [];
            } else {
                $additional_data['customers'] = [];
            }
        }

        $project = $this->get($project_id);
        $members = $this->get_project_members($project_id);

        foreach ($members as $member) {
            if (is_staff_logged_in() && $member['staff_id'] == get_staff_user_id()) {
                continue;
            }
            $mailTemplate = mail_template($staff_template, $project, $member, $additional_data['staff']);
            if (isset($additional_data['attachments'])) {
                foreach ($additional_data['attachments'] as $attachment) {
                    $mailTemplate->add_attachment($attachment);
                }
            }
            $mailTemplate->send();
        }

        if ($action_visible_to_customer == 1) {
            $contacts = $this->clients_model->get_contacts_for_project_notifications($project_id, 'project_emails');

            foreach ($contacts as $contact) {
                if (is_client_logged_in() && $contact['id'] == get_contact_user_id()) {
                    continue;
                }
                $mailTemplate = mail_template($customer_template, $project, $contact, $additional_data['customers']);
                if (isset($additional_data['attachments'])) {
                    foreach ($additional_data['attachments'] as $attachment) {
                        $mailTemplate->add_attachment($attachment);
                    }
                }
                $mailTemplate->send();
            }
        }
    }

    private function _get_project_billing_data($id)
    {
        $this->db->select('billing_type,project_rate_per_hour');
        $this->db->where('id', $id);

        return $this->db->get(db_prefix() . 'projects')->row();
    }

    public function total_logged_time_by_billing_type($id, $conditions = [])
    {
        $project_data = $this->_get_project_billing_data($id);
        $data = [];
        if ($project_data->billing_type == 2) {
            $seconds = $this->total_logged_time($id);
            $data = $this->projects_model->calculate_total_by_project_hourly_rate($seconds, $project_data->project_rate_per_hour);
            $data['logged_time'] = $data['hours'];
        } elseif ($project_data->billing_type == 3) {
            $data = $this->_get_data_total_logged_time($id);
        }

        return $data;
    }

    public function data_billable_time($id)
    {
        return $this->_get_data_total_logged_time($id, [
            'billable' => 1,
        ]);
    }

    public function data_billed_time($id)
    {
        return $this->_get_data_total_logged_time($id, [
            'billable' => 1,
            'billed' => 1,
        ]);
    }

    public function data_unbilled_time($id)
    {
        return $this->_get_data_total_logged_time($id, [
            'billable' => 1,
            'billed' => 0,
        ]);
    }

    private function _delete_discussion_comments($id, $type)
    {
        $this->db->where('discussion_id', $id);
        $this->db->where('discussion_type', $type);
        $comments = $this->db->get(db_prefix() . 'projectdiscussioncomments')->result_array();
        foreach ($comments as $comment) {
            $this->delete_discussion_comment_attachment($comment['file_name'], $id);
        }
        $this->db->where('discussion_id', $id);
        $this->db->where('discussion_type', $type);
        $this->db->delete(db_prefix() . 'projectdiscussioncomments');
    }

    private function _get_data_total_logged_time($id, $conditions = [])
    {
        $project_data = $this->_get_project_billing_data($id);
        $tasks = $this->get_tasks($id, $conditions);

        if ($project_data->billing_type == 3) {
            $data = $this->calculate_total_by_task_hourly_rate($tasks);
            $data['logged_time'] = seconds_to_time_format($data['total_seconds']);
        } elseif ($project_data->billing_type == 2) {
            $seconds = 0;
            foreach ($tasks as $task) {
                $seconds += $task['total_logged_time'];
            }
            $data = $this->calculate_total_by_project_hourly_rate($seconds, $project_data->project_rate_per_hour);
            $data['logged_time'] = $data['hours'];
        }

        return $data;
    }

    private function _update_discussion_last_activity($id, $type)
    {
        if ($type == 'file') {
            $table = db_prefix() . 'project_files';
        } elseif ($type == 'regular') {
            $table = db_prefix() . 'projectdiscussions';
        }
        $this->db->where('id', $id);
        $this->db->update($table, [
            'last_activity' => date('Y-m-d H:i:s'),
        ]);
    }

    public function send_project_email_mentioned_users($project_id, $staff_template, $staff, $additional_data = [])
    {
        $this->load->model('staff_model');

        $project = $this->get($project_id);

        foreach ($staff as $staffId) {
            if (is_staff_logged_in() && $staffId == get_staff_user_id()) {
                continue;
            }
            $member = (array)$this->staff_model->get($staffId);
            $member['staff_id'] = $member['staffid'];

            $mailTemplate = mail_template($staff_template, $project, $member, $additional_data['staff']);
            if (isset($additional_data['attachments'])) {
                foreach ($additional_data['attachments'] as $attachment) {
                    $mailTemplate->add_attachment($attachment);
                }
            }
            $mailTemplate->send();
        }
    }

    public function convert_estimate_items_to_tasks($project_id, $items, $assignees, $project_data, $project_settings)
    {
        $this->load->model('tasks_model');
        foreach ($items as $index => $itemId) {
            $this->db->where('id', $itemId);
            $_item = $this->db->get(db_prefix() . 'itemable')->row();

            $data = [
                'billable' => 'on',
                'name' => $_item->description,
                'description' => $_item->long_description,
                'startdate' => _d($project_data['start_date']),
                'duedate' => '',
                'rel_type' => 'project',
                'rel_id' => $project_id,
                'hourly_rate' => $project_data['billing_type'] == 3 ? $_item->rate : 0,
                'priority' => get_option('default_task_priority'),
                'withDefaultAssignee' => false,
            ];

            if (isset($project_settings->view_tasks)) {
                $data['visible_to_client'] = 'on';
            }

            $task_id = $this->tasks_model->add($data);

            if ($task_id) {
                $staff_id = $assignees[$index];

                $this->tasks_model->add_task_assignees([
                    'taskid' => $task_id,
                    'assignee' => intval($staff_id),
                ]);

                if (!$this->is_member($project_id, $staff_id)) {
                    $this->db->insert(db_prefix() . 'project_members', [
                        'project_id' => $project_id,
                        'staff_id' => $staff_id,
                    ]);
                }
            }
        }
    }

    /**
     * @deprecated
     *
     * @param int $id
     * @param string $type
     *
     * @return array
     */
    public function get_project_overview_weekly_chart_data($id, $type = 'this_week')
    {
        _deprecated_function('Projects_model::get_project_overview_weekly_chart_data', '2.9.2', 'HoursOverviewChart class');

        return (new HoursOverviewChart($id, $type))->get();
    }

    /**
     * @deprecated
     *
     * @param array $filters
     *
     * @return array
     */
    public function get_all_projects_gantt_data($filters = [])
    {
        _deprecated_function('Projects_model::get_all_projects_gantt_data', '2.9.2', 'AllProjectsGantt class');

        return (new AllProjectsGantt($filters))->get();
    }

    /**
     * @deprecated
     *
     * @return array
     */
    public function get_gantt_data($project_id, $type = 'milestones', $taskStatus = null, $type_where = [])
    {
        _deprecated_function('Projects_model::get_gantt_data', '2.9.2', 'Gantt class');

        return (new Gantt($project_id, $type))->forTaskStatus($taskStatus)
            ->excludeMilestonesFromCustomer(isset($type_where['hide_from_customer']) && $type_where['hide_from_customer'] == 1)
            ->get();
    }
}
