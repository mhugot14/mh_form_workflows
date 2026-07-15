<?php
/**
 * Gemeinsames <style>-Fragment für die 8 Absentismus-PDF-Templates.
 * Per include in pdf-header.php eingebunden.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
@page { margin: 1.2cm 1.5cm; }
body { font-family: Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.3; }

table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
td, th { border: 1px solid black; padding: 5px 8px; vertical-align: top; }
table.no-border, table.no-border td { border: none; }

.header { font-weight: bold; font-size: 15pt; margin-bottom: 4px; color: #003E7E; }
.subheader { font-size: 9pt; color: #444; margin-bottom: 15px; }
.section-title { font-weight: bold; margin-top: 12px; margin-bottom: 4px; }
.small-text { font-size: 8pt; color: #444; }

#footer {
	position: fixed; bottom: -35px; left: 0; right: 0; height: 20px;
	color: #555; font-size: 8pt; text-align: center;
	border-top: 1px solid #ccc; padding-top: 5px;
}
