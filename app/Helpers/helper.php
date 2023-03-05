<?php
function unique_random_number()
{
    return substr(number_format(time() * rand(), 0, '', ''), 0, 10);
}
