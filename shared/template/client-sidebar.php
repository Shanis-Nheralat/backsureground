<!-- Add this to the existing client menu structure -->

<?php
// Task management menu items (add this to the client menu array)
$client_menu_items[] = [
    'id' => 'tasks',
    'title' => 'Tasks',
    'icon' => 'fas fa-clipboard-list',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'submit_task',
            'title' => 'Submit New Task',
            'url' => '/client/tasks/submit-task.php'
        ],
        [
            'id' => 'task_history',
            'title' => 'My Tasks',
            'url' => '/client/tasks/history.php'
        ]
    ]
];
?>