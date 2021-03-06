<?php

namespace App\Http\Controllers\Content;

use App\Board;
use App\BoardTag;
use App\Http\Controllers\Board\BoardStats;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Request;

/**
 * Lists all public boards on the system with pertinent stats.
 *
 * @category   Controller
 *
 * @author     Joshua Moon <josh@jaw.sh>
 * @copyright  2016 Infinity Next Development Group
 * @license    http://www.gnu.org/licenses/agpl-3.0.en.html AGPL3
 *
 * @since      0.5.1
 */
class BoardlistController extends Controller
{
    use BoardStats;

    /**
     * View file for the main index page container.
     *
     * @var string
     */
    const VIEW_INDEX = 'boardlist';

    /**
     * Show board list to the user, either rendering the full blade template or just the json.
     *
     * @return Response|JSON
     */
    public function getIndex()
    {
        if (Request::wantsJson()) {
            return $this->boardListJson();
        }

        return $this->makeView(static::VIEW_INDEX, [
            'boards' => $this->boardListSearch(),
            'stats' => $this->boardStats(),
            'tags' => $this->boardListTags(),
            'input' => $this->boardListInput(),
        ]);
    }

    protected function boardListInput()
    {
        $input = Request::only('page', 'sfw', 'title', 'lang', 'tags', 'sort', 'sortBy');

        $input['page'] = isset($input['page'])  ? max((int) $input['page'], 1) : 1;
        $input['sfw'] = isset($input['sfw'])   ? (bool) $input['sfw'] : false;
        $input['title'] = isset($input['title']) ? $input['title'] : false;
        $input['lang'] = isset($input['lang'])  ? $input['lang'] : false;

        if (isset($input['tags'])) {
            if (is_string($input['tags'])) {
                $input['tags'] = str_replace(['+', '-', ' '], ',', $input['tags']);
                $input['tags'] = explode(',', $input['tags']);
            }

            if (is_array($input['tags'])) {
                $input['tags'] = array_filter($input['tags']);
            }
        } else {
            $input['tags'] = [];
        }

        if (isset($input['sort']) && in_array($input['sort'], ['stats_ppd', 'stats_plh', 'stats_active_users', 'posts_total'])) {
            $input['sortBy'] = $input['sortBy'] == 'asc' ? 'asc' : 'desc';
        } else {
            $input['sort'] = 0;
            $input['sortBy'] = 'desc';
        }

        return $input;
    }

    protected function boardListJson()
    {
        $boards = $this->boardListSearch();
        $stats = $this->boardStats();
        $tags = $this->boardListTags();
        $input = $this->boardListInput();

        $items = new Collection($boards->items());
        $items = $items->toArray();

        foreach ($items as &$item) {
            unset($item['settings']);
            unset($item['stats']);
        }

        return json_encode([
            'boards' => $items,
            'current_page' => (int) $boards->currentPage(),
            'per_page' => (int) $boards->perPage(),
            'total' => $boards->total(),
            'omitted' => (int) max(0, $boards->total() - ($boards->currentPage() * $boards->perPage())),
            'tagWeght' => $tags,
            'search' => [
                'lang' => $input['lang'] ?: '',
                'page' => $input['page'] ?: 1,
                'tags' => $input['tags'] ?: [],
                'time' => Carbon::now()->timestamp,
                'title' => $input['title'] ?: '',
                'sfw' => (bool) $input['sfw'],
                'sort' => $input['sort'],
                'sortBy' => $input['sortBy'],
            ],
        ]);
    }

    protected function boardListSearch($perPage = 50)
    {
        $input = $this->boardListInput();

        $title = $input['title'];
        $lang = $input['lang'];
        $page = $input['page'];
        $tags = collect($input['tags']);
        $sfw = $input['sfw'];
        $sort = $input['sort'];
        $sortBy = $input['sortBy'];

        $boards = collect(Board::getBoardsForBoardlist());

        $boards = $boards->filter(function ($item) use ($lang, $tags, $sfw, $title) {
            // Are we able to view unindexed boards?
            if (!$item['is_indexed'] && !user()->canViewUnindexedBoards()) {
                return false;
            }

            // Are we requesting SFW only?
            if ($sfw && !$item['is_worksafe']) {
                return false;
            }

            // Are we searching by language?
            if ($lang) {
                $boardLang = '';

                if (isset($item['settings']) && isset($item['settings']['boardLanguage'])) {
                    $boardLang = $item['settings']['boardLanguage'];
                }

                if ($lang != $boardLang) {
                    return false;
                }
            }

            // Are we searching tags?
            if ($tags->count() && $tags->intersect(Arr::pluck($item['tags'], 'tag'))->count() < $tags->count()) {
                return false;
            }

            // Are we searching titles and descriptions?
            if ($title && stripos($item['board_uri'], $title) === false && stripos($item['title'], $title) === false && stripos($item['description'], $title) === false) {
                return false;
            }

            return true;
        });

        if ($title || ($sort && $sortBy)) {
            $sortWeight = $sortBy == 'asc' ? -1 : 1;

            $boards = $boards->sort(function ($a, $b) use ($title, $sort, $sortWeight) {
                // Sort by active users, then last post time.
                $aw = 0;
                $bw = 0;

                if ($title) {
                    $aw += ($a['board_uri'] === $title)                   ? 80 : 0;
                    $aw += (stripos($a['board_uri'], $title) !== false)   ? 40 : 0;
                    $aw += (stripos($a['title'], $title) !== false)       ? 20 : 0;
                    $aw += (stripos($a['description'], $title) !== false) ? 10 : 0;

                    $bw += ($b['board_uri'] === $title)                   ? 80 : 0;
                    $aw += (stripos($b['board_uri'], $title) !== false)   ? 40 : 0;
                    $aw += (stripos($b['title'], $title) !== false)       ? 20 : 0;
                    $aw += (stripos($b['description'], $title) !== false) ? 10 : 0;
                }

                if ($sort) {
                    if ($a[$sort] > $b[$sort]) {
                        $aw += $sortWeight;
                    } elseif ($a[$sort] < $b[$sort]) {
                        $bw += $sortWeight;
                    }
                }

                return $bw - $aw;
            });
        }


        $paginator = new LengthAwarePaginator(
            $boards->forPage($page, $perPage),
            $boards->count(),
            $perPage,
            $page
        );
        $paginator->setPath(url('boards.html'));

        foreach ($input as $inputIndex => $inputValue) {
            if ($inputIndex == 'sfw') {
                $inputIndex = (int) (bool) $inputValue;
            }

            $paginator->appends($inputIndex, $inputValue);
        }


        return $paginator;
    }

    protected function boardListTags()
    {
        $tags = BoardTag::distinct('tag')->with([
            'boards',
            'boards.stats',
            'boards.stats.uniques',
        ])->limit(50)->get();

        $tagWeight = $tags->sortByDesc('weight')->pluck('weight', 'tag')->toArray();

        return $tagWeight;
    }
}
