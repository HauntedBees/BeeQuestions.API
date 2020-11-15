<?php declare(strict_types=1);
/**
 * Bee Questions
 * @copyright 2020 Haunted Bees Productions
 * @author Sean Finch <fench@hauntedbees.com>
 * @license https://www.gnu.org/licenses/agpl-3.0.en.html GNU Affero General Public License
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @see https://github.com/HauntedBees/BeeAPI
 */
class QuestionsSecureController extends BeeSecureController {
    public function __construct() { parent::__construct("bqdb", BEEROLE_ADMIN); }
    public function GetUsers(string $searchQuery = "") {
        $whereQuery = empty($searchQuery) ? "" : "WHERE u.displayname LIKE :d";
        $params = empty($searchQuery) ? [] : ["d" => "$searchQuery%"];
        return $this->response->OK($this->db->GetDataTable(
            "SELECT u.id, u.displayname, u.lastlogin, u.score, u.level, COUNT(DISTINCT q.id) questionsThisWeek, COUNT(DISTINCT a.id) answersThisWeek,
                CASE
                    WHEN u.blockeduntil IS NULL THEN NULL
                    WHEN u.blockeduntil < NOW() THEN NULL
                    ELSE u.blockeduntil
                END AS blockdate
            FROM users u
                LEFT JOIN question q ON q.user = u.id AND q.posted >= DATE_ADD(NOW(), INTERVAL -1 WEEK)
                LEFT JOIN answer a ON a.user = u.id AND a.opened >= DATE_ADD(NOW(), INTERVAL -1 WEEK)
            $whereQuery
            GROUP BY u.id
            ORDER BY u.lastlogin DESC
            LIMIT 25", $params));
    }
    public function GetReports() {
        return $this->response->OK($this->db->GetDataTable(
            "SELECT 
                ur.id,
                CASE
                    WHEN ur.reportedanswer IS NOT NULL THEN 'answer'
                    WHEN ur.reportedquestion IS NOT NULL THEN 'question'
                    WHEN ur.reporteduser IS NOT NULL THEN 'user'
                END AS type,
                CASE
                    WHEN ur.reportedanswer IS NOT NULL THEN a.answer
                    WHEN ur.reportedquestion IS NOT NULL THEN q.question
                    WHEN ur.reporteduser IS NOT NULL THEN ru.displayname
                END AS value,
                ur.reported,
                u.displayname
            FROM user_report ur
                INNER JOIN users u ON ur.user = u.id
                LEFT JOIN answer a ON ur.reportedanswer = a.id
                LEFT JOIN question q ON ur.reportedquestion = q.id
                LEFT JOIN users ru ON ur.reporteduser = ru.id
            WHERE dismissed = 0
            ORDER BY reported DESC
            LIMIT 25"));
    }
    public function PostBlock(BQBlockUser $block, bool $response = true) {
        $blockSQL = "";
        switch($block->blocktype) {
            case "un": $blockSQL = "NULL"; break;
            case "dy": $blockSQL = "DATE_ADD(NOW(), INTERVAL 1 DAY)"; break;
            case "wk": $blockSQL = "DATE_ADD(NOW(), INTERVAL 1 WEEK)"; break;
            case "mn": $blockSQL = "DATE_ADD(NOW(), INTERVAL 1 MONTH)"; break;
            case "yr": $blockSQL = "DATE_ADD(NOW(), INTERVAL 1 YEAR)"; break;
            default: throw new BeeException("Invalid block type.");
        }
        $this->db->ExecuteNonQuery("UPDATE users SET blockeduntil = $blockSQL WHERE id = :i", ["i" => $block->userID]);
        if($response) {
            $this->response->Message("Block status changed successfully.");
        }
    }
    public function PostReport(BQHandleReport $report) {
        if($report->remove) {
            $res = $this->db->GetDataRow("SELECT reporteduser, reportedanswer, reportedquestion FROM user_report WHERE id = :i", ["i" => $report->id]);
            if($res["reporteduser"] !== null) {
                $block = new BQBlockUser();
                $block->blocktype = "wk";
                $block->userID = intval($res["reporteduser"]);
                $this->PostBlock($block, false);
            } else if($res["reportedanswer"] !== null) {
                $this->db->ExecuteNonQuery("UPDATE answer SET status = 1, closed = NOW(), answer = '[This post has been deleted by an administrator]' WHERE id = :i", ["i" => intval($res["reportedanswer"])]);
            } else if($res["reportedquestion"] !== null) {
                $this->db->ExecuteNonQuery("UPDATE question SET question = '[This post has been deleted by an administrator]' WHERE id = :i", ["i" => intval($res["reportedquestion"])]);
            }
        }
        $this->db->ExecuteNonQuery("UPDATE user_report SET dismissed = 1 WHERE id = :i", ["i" => $report->id]);
        return $this->GetReports();
    }
}