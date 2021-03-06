<?php

/* ========================================================================
 * Open eClass 3.0
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2014  Greek Universities Network - GUnet
 * A full copyright notice can be read in "/info/copyright.txt".
 * For a full list of contributors, see "credits.txt".
 *
 * Open eClass is an open platform distributed in the hope that it will
 * be useful (without any warranty), under the terms of the GNU (General
 * Public License) as published by the Free Software Foundation.
 * The full license can be read in "/info/license/license_gpl.txt".
 *
 * Contact address: GUnet Asynchronous eLearning Group,
 *                  Network Operations Center, University of Athens,
 *                  Panepistimiopolis Ilissia, 15784, Athens, Greece
 *                  e-mail: info@openeclass.org
 * ======================================================================== */

/**
 * @file listcours.php
 * @brief display list of courses
 */
$require_departmentmanage_user = true;

require_once '../../include/baseTheme.php';
require_once 'include/lib/hierarchy.class.php';
require_once 'include/lib/course.class.php';
require_once 'include/lib/user.class.php';
require_once 'hierarchy_validations.php';


if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $tree = new Hierarchy();
    $course = new Course();
    $user = new User();

    // A search has been submitted
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $searchurl = "&search=yes";
    $searchtitle = isset($_GET['formsearchtitle']) ? $_GET['formsearchtitle'] : '';
    $searchcode = isset($_GET['formsearchcode']) ? $_GET['formsearchcode'] : '';
    $searchtype = isset($_GET['formsearchtype']) ? intval($_GET['formsearchtype']) : '-1';
    $searchfaculte = isset($_GET['formsearchfaculte']) ? intval(getDirectReference($_GET['formsearchfaculte'])) : '';
    // pagination
    $limit = intval($_GET['iDisplayLength']);
    $offset = intval($_GET['iDisplayStart']);
    // Search for courses
    $query = '';
    $terms = array();
    if (!empty($searchtitle)) {
        $query .= ' AND title LIKE ?s';
        $terms[] = '%' . $searchtitle . '%';
    }
    if (!empty($searchcode)) {
        $query .= ' AND (course.code LIKE ?s OR public_code LIKE ?s)';
        $terms[] = '%' . $searchcode . '%';
        $terms[] = '%' . $searchcode . '%';
    }
    if ($searchtype != "-1") {
        $query .= ' AND visible = ?d';
        $terms[] = $searchtype;
    }

    if ($searchfaculte) {
        $subs = $tree->buildSubtrees(array($searchfaculte));
        $ids = 0;
        foreach ($subs as $key => $id) {
            $terms[] = $id;
            $ids++;
        }
        $query .= ' AND hierarchy.id IN (' . implode(', ', array_fill(0, $ids, '?d')) . ')';
    }
    if (isset($_GET['reg_flag']) and ! empty($_GET['date'])) {
        $query .= ' AND created ' . (($_GET['reg_flag'] == 1) ? '>=' : '<=') . ' ?s';
        $date_created_at = DateTime::createFromFormat("d-m-Y H:i", $_GET['date']);
        $terms[] = $date_created_at->format("Y-m-d H:i:s");
    }

    // Datatables internal search
    $filter_terms = array();
    if (!empty($_GET['sSearch'])) {
        $filter_query = ' AND (title LIKE ?s OR prof_names LIKE ?s)';
        $filter_terms[] = '%' . $_GET['sSearch'] . '%';
        $filter_terms[] = '%' . $_GET['sSearch'] . '%';
    } else {
        $filter_query = '';
    }

    // Limit department admin search only to subtrees of own departments
    if (isDepartmentAdmin()) {
        $begin = true;
        foreach ($user->getDepartmentIds($uid) as $department) {
            if ($begin) {
                $query .= ' AND (';
                $begin = false;
            } else {
                $query .= ' OR ';
            }
            $nodeLftRgt = $tree->getNodeLftRgt($department);
            $query .= 'hierarchy.lft BETWEEN ' . $nodeLftRgt->lft . ' AND ' . $nodeLftRgt->rgt;
        }
        $query .= ')';
    }

    // sorting
    $extra_query = "ORDER BY course.title " .
            ($_GET['sSortDir_0'] == 'desc' ? 'DESC' : '');
    // pagination
    if ($limit > 0) {
        $extra_query .= " LIMIT ?d, ?d";
        $extra_terms = array($offset, $limit);
    } else {
        $extra_terms = array();
    }

    $sql = Database::get()->queryArray("SELECT DISTINCT course.code, course.title, course.prof_names, course.visible, course.id
                               FROM course, course_department, hierarchy
                              WHERE course.id = course_department.course
                                AND hierarchy.id = course_department.department
                                    $query $filter_query $extra_query", $terms, $filter_terms, $extra_terms);
    $all_results = Database::get()->querySingle("SELECT COUNT(*) as total FROM course, course_department, hierarchy
                                                WHERE course.id = course_department.course
                                                AND hierarchy.id = course_department.department
                                                $query", $terms)->total;
    $filtered_results = Database::get()->querySingle("SELECT COUNT(*) as total FROM course, course_department, hierarchy
                                                WHERE course.id = course_department.course
                                                AND hierarchy.id = course_department.department
                                                $query $filter_query", $terms, $filter_terms)->total;

    $data['iTotalRecords'] = $all_results;
    $data['iTotalDisplayRecords'] = $filtered_results;

    $data['aaData'] = array();
        
    foreach ($sql as $logs) {        
        $course_title = "<a href='{$urlServer}courses/" . $logs->code . "/'><b>" . q($logs->title) . "</b>
                        </a> (" . q($logs->code) . ")<br /><i>" . q($logs->prof_names) . "";
        // Define course type
        switch ($logs->visible) {
            case COURSE_CLOSED:
                $icon = 'lock_closed';
                $title = $langClosedCourse;
                break;
            case COURSE_REGISTRATION:
                $icon = 'lock_registration';
                $title = $langRegCourse;
                break;
            case COURSE_OPEN:
                $icon = 'lock_open';
                $title = $langOpenCourse;
                break;
            case COURSE_INACTIVE:
                $icon = 'lock_inactive';
                $title = $langInactiveCourse;
                break;
        }

        $departments = $course->getDepartmentIds($logs->id);
        $i = 1;
        $dep = '';
        foreach ($departments as $department) {
            $br = ($i < count($departments)) ? '<br/>' : '';
            $dep .= $tree->getFullPath($department) . $br;
            $i++;
        }
        // display tc_server action if needed
        $tc_type = is_configured_tc_server();
        if ($tc_type == FALSE) {
            $show_tc = false;
            $icon_tc = $link_tc = '';
        } else {
            if (is_tc_server_enabled_for_all($tc_type)) {
                $show_tc = false;
                $icon_tc = $link_tc = '';
            } else {                                
                $show_tc = true;
                if (is_active_tc_server($tc_type, $logs->id)) {
                    $icon_tc = 'fa-check-square-o';
                    $link_tc = "tc=0&amp;tc_type=$tc_type";
                } else {
                    $icon_tc = 'fa-square-o';
                    $link_tc = "tc=1&amp;tc_type=$tc_type";
                }
            }
        }
        
        // Add links to course users, delete course and course edit
        $icon_content = action_button(array(
            array(
                'title' => $langEditChange,
                'icon' => 'fa-edit',
                'url' => "editcours.php?c=$logs->code"
            ),
            array(
                'title' => $langUsers,
                'icon' => 'fa-user',
                'url' => "listusers.php?c=$logs->id"
            ),
            array(
                'title' => $langUsersLog,
                'icon' => 'fa-list',
                'url' => "../usage/displaylog.php?c=$logs->id&amp;from_admin=TRUE",
                'show' => !isDepartmentAdmin()
            ),
            array(
                'title' => $langActivateConference,
                'icon' => $icon_tc,
                'url' => "$_SERVER[SCRIPT_NAME]?c=$logs->id&amp;$link_tc",
                'show' => $show_tc
            ),  
            array(
                'title' => $langDelete,
                'icon' => 'fa-times',
                'url' => "delcours.php?c=" . getIndirectReference($logs->id)
            )            
        ));
        $data['aaData'][] = array(
            '0' => $course_title,
            '1' => icon_old_style($icon, $title),
            '2' => $dep,
            '3' => $icon_content
        );
    }
    echo json_encode($data);
    exit();
}

load_js('tools.js');
load_js('datatables');
$head_content .= "<script type='text/javascript'>
        $(document).ready(function() {
            $('#course_results_table').DataTable ({
                ".(($is_editor)?"'aoColumnDefs':[{'sClass':'option-btn-cell', 'aTargets':[-1]}],":"")."            
                'bProcessing': true,
                'bServerSide': true,
                'sAjaxSource': '$_SERVER[REQUEST_URI]',
                'aLengthMenu': [
                   [10, 15, 20 , -1],
                   [10, 15, 20, '$langAllOfThem'] // change per page values here
                ],
                'sPaginationType': 'full_numbers',
                'bAutoWidth': false,
                'searchDelay': 1000,
                'aoColumns': [
                    {'bSortable' : true, 'sWidth': '50%' },
                    {'bSortable' : false, 'sClass': 'center' },
                    {'bSortable' : false, 'sWidth': '25%' },
                    {'bSortable' : false },
                ],
                'fnDrawCallback': function( oSettings ) {
                    popover_init();
                },
                'oLanguage': {
                   'sLengthMenu':   '$langDisplay _MENU_ $langResults2',
                   'sZeroRecords':  '" . $langNoResult . "',
                   'sInfo':         '$langDisplayed _START_ $langTill _END_ $langFrom2 _TOTAL_ $langTotalResults',
                   'sInfoEmpty':    '$langDisplayed 0 $langTill 0 $langFrom2 0 $langResults2',
                   'sInfoFiltered': '',
                   'sInfoPostFix':  '',
                   'sSearch':       '" . $langSearch . "',
                   'sUrl':          '',
                   'oPaginate': {
                       'sFirst':    '&laquo;',
                       'sPrevious': '&lsaquo;',
                       'sNext':     '&rsaquo;',
                       'sLast':     '&raquo;'
                   }
               }
            });
            $('.dataTables_filter input').attr('placeholder', '$langTitle, $langTeacher');
        });
        </script>";


// enable - disable tc server per course
if (isset($_GET['tc'])) {
    if ($_GET['tc'] == 1) {
        $tc_id = Database::get()->querySingle("SELECT id FROM tc_servers WHERE enabled='true' AND `type` = ?s ORDER BY weight ASC", $_GET['tc_type'])->id;        
        if ($tc_id) {
            Database::get()->query("INSERT INTO course_external_server SET course_id=?d, external_server=?d", $_GET['c'], $tc_id);
            Session::Messages($langTcCourseEnabled, 'alert alert-info');
        }
    } elseif($_GET['tc'] == 0)  {
        Database::get()->query("DELETE FROM course_external_server WHERE course_id = ?d", $_GET['c']);
        Session::Messages($langTcCourseDisabled, 'alert alert-info');        
    } else {
        redirect_to_home_page('modules/admin/index.php');
    }
}

$navigation[] = array('url' => 'index.php', 'name' => $langAdmin);
$navigation[] = array('url' => 'searchcours.php', 'name' => $langSearchCourses);
$toolName = $langListCours;

// Display Actions Toolbar
$data['action_bar'] = action_bar(array(
            array('title' => $langAllCourses,
                'url' => "$_SERVER[SCRIPT_NAME]?formsearchtitle=&amp;formsearchcode=&amp;formsearchtype=-1&amp;reg_flag=1&amp;date=&amp;formsearchfaculte=" . getIndirectReference(0) . "&amp;search_submit=$langSearch",
                'icon' => 'fa-search',
                'level' => 'primary-label'),            
            array('title' => $langReturnSearch,
                'url' => "searchcours.php",
                'icon' => 'fa-reply',
                'level' => 'primary')));                    

$data['menuTypeID'] = 3;
view('admin.courses.listcours', $data);
