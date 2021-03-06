<?php
/**
 * @copyright 2015 City of Bloomington, Indiana
 * @license http://www.gnu.org/licenses/agpl.txt GNU/AGPL, see LICENSE.txt
 * @author Cliff Ingham <inghamn@bloomington.in.gov>
 * @param Event $this->event
 */
use Application\Models\Person;
?>
<?php
    $return_uri = !empty($_GET['return_uri']) ? $_GET['return_uri'] : BASE_URI.'/events';
    $helper = $this->template->getHelper('buttonLink');
    echo $helper->buttonLink(
        $return_uri,
        $this->template->_('back'),
        'back'
    );
?>
<h1 class="headerBar-title"><?= $this->template->title; ?></h1>
<div class="headerBar-tools">
    <?php
        $id = $this->event->getId()
            ? $this->event->getId()
            : $this->event->getGoogle_event_id();

        if (Person::isAllowed('events', 'update')) {
            echo $helper->buttonLink(
                BASE_URI.'/events/update?id='.$id,
                $this->template->_('event_edit'),
                'edit'
            );
        }
        if (Person::isAllowed('events', 'delete')) {
            $uri = BASE_URI.'/events/delete?id='.$id;
            echo "
            <a href=\"$uri\" class=\"delete button\"
                onclick=\"return PHP.deleteConfirmation();\">
                {$this->_('event_delete')}
            </a>";
        }
    ?>
</div>
