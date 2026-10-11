<?php

// Desktop (Electron) hardware: receipt printer test slip and the per-device cash drawer (APP-5).
return [
    'drawer_pin_label' => 'Cash drawer connector pin',
    'drawer_pin_option' => 'Pin :pin',
    'drawer_pin_hint' => 'Saved on this device only. Most drawers use pin 2; try pin 5 if the drawer does not open.',
    'drawer_pin_saved' => 'Drawer pin :pin saved on this device.',
    'drawer_pin_save_failed' => 'Could not save the drawer pin on this device.',
    'drawer_testing' => 'Opening drawer...',
    'drawer_test_success_escpos' => 'The open command (pin :pin) was sent to the receipt printer. If the drawer did not open, try the other pin.',
    'drawer_test_success_driver' => 'The printer refused the direct command, so the drawer signal was sent through the printer driver. Make sure the driver is set to open the drawer after printing.',
    'drawer_error_no_printer' => 'No printer is installed on this device.',
    'drawer_error_printer_not_found' => 'The selected receipt printer was not found. Choose it again from the list.',
    'drawer_error_failed' => 'The drawer could not be opened. Check the printer connection and the drawer cable.',
    'test_slip_title' => 'Direct thermal print test',
    'test_slip_connection' => 'Connection',
    'test_slip_connected' => 'Connected',
    'test_slip_paper_width' => 'Paper width',
    'test_slip_footer' => 'Printed silently by the desktop app.',
];
