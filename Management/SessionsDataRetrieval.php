<?php

/**
 * This file is part of the UserSessionsManagement plugin for ILIAS.
 * ILIAS is a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * UserSessionsManagement is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 *********************************************************************/

declare(strict_types=1);

namespace kergomard\UserSessionManagement\Management;

use kergomard\UserSessionManagement\Config\Config;

use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\Data\Range;
use ILIAS\Data\Order;

class SessionsDataRetrieval implements DataRetrieval
{
    private \ilObjCourse $object;

    /**
     *
     * @var array<string, string|array>
     */
    private array $filter_data;

    private ?array $course_member_ids = null;

    public function __construct(
        private UserSessionRepository $user_session_repo,
    ) {
    }

    public function withObject(\ilObjCourse $object): self {
        $clone = clone $this;
        $clone->object = $object;
        return $clone;
    }

    /**
     *
     * @param array<string, string|array> $filter_data
     * @return self
     */
    public function withFilterData (array $filter_data): self {
        $clone = clone $this;
        $clone->filter_data = $filter_data;
        return $clone;
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): \Generator {
        $course_members =$this->sortAndLimitCourseMembers(
            $this->filterCourseMembers(
                \ilObjUser::_getUsersForIds($this->getCourseMemberIds())
            ),
            $range,
            $order
        );
        $this->user_session_repo->preloadDataForUserIds($this->getCourseMemberIds());

        foreach($course_members as $course_member) {
            yield $this->user_session_repo->getTableRowForUser(
                $row_builder,
                $course_member
            );
        }
    }

    public function getTotalRowCount(
        ?array $filter_data,
        ?array $additional_parameters
    ): ?int {
        return count($this->getCourseMemberIds());
    }

    private function filterCourseMembers(array $course_members):  array {
        $filter_values = array_filter(
            $this->filter_data,
            static fn(?string $v): bool => $v !== null && $v !== ''
        );

        if ($filter_values === []) {
            return $course_members;
        }

        return array_filter(
            $course_members,
            static function(array $v)use ($filter_values): bool {
                foreach ($filter_values as $key => $value) {
                    if (stristr($v[$key], $value) === false) {
                        return false;
                    }
                }
                return true;
            }
        );
    }

    private function sortAndLimitCourseMembers(
        array $course_members,
        Range $range,
        Order $order
    ): array {
        $order_array = $order->get();
        usort(
            $course_members,
            static function (array $a, array $b) use ($order_array): int {
                foreach($order_array as $key => $direction) {
                    if ($direction === 'ASC') {
                        $relative_position = strcasecmp($a[$key], $b[$key]);
                    } else {
                        $relative_position = strcasecmp($b[$key], $a[$key]);
                    }
                    if ($relative_position > 0) {
                        return $relative_position;
                    }

                }
                return $relative_position;
            }
        );
        return array_slice($course_members, $range->getStart(), $range->getLength());
    }

    private function getCourseMemberIds(): array
    {
        if ($this->course_member_ids === null) {
            $this->course_member_ids = $this->object->getMembersObject()->getMembers();
        }

        return $this->course_member_ids;
    }
}
