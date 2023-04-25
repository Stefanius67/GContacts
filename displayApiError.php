<?php
declare(strict_types=1);

/**
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
function displayApiError(string $strAction, string $strResourceName, int $iResponseCode, string $strMessage, string $strStatus) : void
{
    $strHTML =<<<HTML
        <html>
        <head>
        <style>
        body, table {
            font-family: Sans-Serif;
            font-size: 12px;
        }
        table {
            border-spacing: 0;
            border-collapse: collapse;
        }
        td {
            border: 1px solid #ccc;
            padding: 2px 4px;
        }
        tr td:nth-child(1) {
            font-weight: bold;
        }
        </style>
        </head>
        <body>
        <h2>Error $strAction</h2>
        <table>
        	<tbody>
        		<tr>
        			<td>ressourceName</td>
        			<td>$strResourceName</td>
        		</tr>
        		<tr>
        			<td>Responsecode</td>
        			<td>$iResponseCode</td>
        		</tr>
        		<tr>
        			<td>Message</td>
        			<td>$strMessage</td>
        		</tr>
        		<tr>
        			<td>Status</td>
        			<td>$strStatus</td>
        		</tr>
        	</tbody>
        </table>
        </body>
        </html>
        HTML;

    echo $strHTML;
}