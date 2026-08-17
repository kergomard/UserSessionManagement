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
        private \ilObjUser $current_user,
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
        $course_members =$this->orderAndLimitCourseMembers(
            $this->filterCourseMembers(
                \ilObjUser::_getUsersForIds($this->getCourseMemberIds())
            ),
            $range,
            $order
        );
        $this->user_session_repo->preloadDataForUserIds($this->getCourseMemberIds());

        $current_user_timezone = new \DateTimeZone(
            $this->current_user->getTimeZone()
        );
        foreach($course_members as $course_member) {
            yield $this->user_session_repo->getSessionForUserId(
                $course_member['usr_id']
            )->getAsTableRow(
                $row_builder,
                $course_member,
                $current_user_timezone
            );
        }
    }

    public function getTotalRowCount(
        ?array $filter_data,
        ?array $additional_parameters
    ): ?int {
        return count($this->getCourseMemberIds());
    }

    /**
     * @return array<int>
     */
    public function getAccessibleAndFilteredMemberIds(): array
    {
        return array_map(
            fn(array $v): int => $v['usr_id'],
            $this->filterCourseMembers(
                \ilObjUser::_getUsersForIds($this->getCourseMemberIds())
            )
        );
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
            function(array $v)use ($filter_values): bool {
                foreach ($filter_values as $key => $filter_value) {
                    if (!$this->isToBeKept($key, $filter_value, $v)) {
                        return false;
                    }
                }
                return true;
            }
        );
    }

    private function orderAndLimitCourseMembers(
        array $course_members,
        Range $range,
        Order $order
    ): array {
        $order_array = $order->get();
        usort(
            $course_members,
            function (array $a, array $b) use ($order_array): int {
                foreach($order_array as $key => $direction) {
                    $relative_position = $this->getRelativPosition(
                        $key,
                        $a,
                        $b,
                        $direction
                    );
                    if ($relative_position > 0) {
                        return $relative_position;
                    }

                }
                return $relative_position;
            }
        );
        return array_slice($course_members, $range->getStart(), $range->getLength());
    }

    private function isToBeKept(
        string $filter_key,
        string $filter_value,
        array $user_values
    ): bool {
        if ($filter_key === ManagementGUI::COLUMN_LOGGED_IN) {
            $is_session_active = $this->user_session_repo
                ->getSessionForUserId($user_values['usr_id'])
                ->isSessionActive();
            $must_be_active = $filter_value === 'y';

            return $is_session_active === $must_be_active;
        }

        if (stristr($user_values[$filter_key], $filter_value) === false) {
            return false;
        }

        return true;
    }

    private function getRelativPosition(
        string $order_key,
        array $user_a_values,
        array $user_b_values,
        string $direction
    ): int {
        // Handle sorting for "Logged In" status
        if ($order_key === ManagementGUI::COLUMN_LOGGED_IN) {
            $user_a_session_active = $this->user_session_repo
                ->getSessionForUserId($user_a_values['usr_id'])
                ->isSessionActive();
            $user_b_session_active = $this->user_session_repo
                ->getSessionForUserId($user_b_values['usr_id'])
                ->isSessionActive();

            if ($user_a_session_active === $user_b_session_active) {
                return 0;
            }

            if ($direction === 'ASC' && $user_a_session_active > $user_b_session_active
                || $direction === 'DSC' && $user_a_session_active < $user_b_session_active) {
                return 1;
            }

            return -1;
        }

        // Handle sorting for IP address
        if ($order_key === ManagementGUI::COLUMN_LAST_LOGIN_IP) {
            $ip_a = $this->user_session_repo->getSessionForUserId($user_a_values['usr_id'])->getLoginIp();
            $ip_b = $this->user_session_repo->getSessionForUserId($user_b_values['usr_id'])->getLoginIp();
            if ($direction === 'ASC') {
                return strnatcasecmp($ip_a, $ip_b);
            }
            return strnatcasecmp($ip_b, $ip_a);
        }

        // Standard alphabetical sorting for all other basic user fields
        $value_a = $user_a_values[$order_key] ?? '';
        $value_b = $user_b_values[$order_key] ?? '';
        if ($direction === 'ASC') {
            return strcasecmp($value_a, $value_b);
        }

        return strcasecmp($value_b, $value_a);
    }

    private function getCourseMemberIds(): array
    {
        if ($this->course_member_ids === null) {
            $this->course_member_ids = $this->object->getMembersObject()->getMembers();
        }

        return $this->course_member_ids;
    }
}
