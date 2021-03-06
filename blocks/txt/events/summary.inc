<?php
/**
 * @copyright 2015-2016 City of Bloomington, Indiana
 * @license http://www.gnu.org/licenses/agpl.txt GNU/AGPL, see LICENSE.txt
 * @param DateTime $start
 * @param DateTime $end
 * @param array $eventList
 */
use Blossom\Classes\Block;
$this->_include('events/partials/emailHeader.inc');
?>

Here are the upcoming road related events for next week, <?= $this->start->format(DATE_FORMAT); ?> to <?= $this->end->format(DATE_FORMAT); ?>:

<?php
    $block = new Block('events/list.inc', ['events'=>$this->eventList]);
    echo $block->render('txt');

    $group = substr(GOOGLE_GROUP, 0, strpos(GOOGLE_GROUP, '@'));
?>

To update your subscription status, please visit:
https://groups.google.com/forum/#!forum/<?= $group; ?>
