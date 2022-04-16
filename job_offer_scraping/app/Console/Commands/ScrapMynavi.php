<?php

namespace App\Console\Commands;

use App\Models\MynaviJobs;
use App\Models\MynaviUrl;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ScrapMynavi extends Command
{
	const HOST = 'https://tenshoku.mynavi.jp';
	const FILE_PATH = 'app/mynavi_jobs.csv';
	const PAGE_NUM = 2;
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'scrape:mynavi';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Scrape Mynavi';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 */
	public function handle()
	{
		$this->truncateTables(); //テスト時はコメントアウト
		$this->saveUrls(); //テスト時はコメントアウト
		$this->saveJobs(); //テスト時はコメントアウト
		$this->exportCsv(); //テスト時はコメントアウト
	}

	private function truncateTables()
	{
		DB::table('mynavi_urls')->truncate(); //一覧ページ内にある各詳細記事URLのテーブルを削除
		DB::table('mynavi_jobs')->truncate(); ////各詳細ページのurl、タイトル、会社名、ジャンルのテーブルを削除
	}

	private function saveUrls() //一覧ページ内にある各詳細記事URLを取得（３０s/p間隔）してDBへ保存する
	{
		foreach (range(1, $this::PAGE_NUM) as $num) {
			$url = $this::HOST . '/list/pg' . $num . '/'; //検索範囲のURL
			$client = new \Goutte\Client(); //定型文
			$crawler = $client->request('GET', $url); //変数を使ってrequestでGETを送る
			$urls = $crawler->filter('.cassetteRecruit__copy > a')->each(function ($node) { //クラス指定でfilterをかける→{}内処理を繰り返す
				$href = $node->attr('href');
				return [
					'url' => substr($href, 0, strpos($href, '/', 1) + 1),
					'created_at' => Carbon::now(),
					'updated_at' => Carbon::now(),
				];
			});
			DB::table('mynavi_urls')->insert($urls);
			sleep(30); // 30秒止まる（テスト時はコメントアウト）
		}
	}

	private function saveJobs() //各詳細ページのurl、タイトル、会社名、ジャンルを取得
	{
		foreach (MynaviUrl::all() as $index => $mynaviUrl) {
			$url = $this::HOST . $mynaviUrl->url;
			$client = new \Goutte\Client(); //定型文
			$crawler = $client->request('GET', $url); //変数を使ってrequestでGETを送る
			MynaviJobs::create([ //create()はセキュリティの関係で複数の値を一度に取れないようになっている→MynaviJobs.phpで追記
				'url' => $url,
				'title' => $this->getTitle($crawler),
				'company_name' => $this->getCompanyName($crawler),
				'features' => $this->getFeatures($crawler),
			]);
			// if ($index > 10) { //テスト用
			// 	break;
			// }
			sleep(30);
		}
	}

	private function getTitle($crawler) //saveJobs()のうちのタイトル取得
	{
		return $crawler->filter('.occName')->text();
	}

	private function getCompanyName($crawler) //saveJobs()のうちの会社名取得
	{
		return $crawler->filter('.companyName')->text();
	}

	private function getFeatures($crawler) //saveJobs()のうちのジャンル取得
	{
		$features = $crawler->filter('.cassetteRecruit__attribute.cassetteRecruit__attribute-jobinfo li.cassetteRecruit__attributeLabel > span') //ジャンルのclass名が他にも使用されていたので親要素から指定
			->each(function ($node) { //ジャンルを配列で取得
				return $node->text();
			});
		return implode(',', $features); //配列を'、'区切りで文字列に変換する
	}

	private function exportCsv() //csvファイルとして取得したデータを出力
	{
		$file = fopen(storage_path($this::FILE_PATH), 'w'); //ファイルのパス（場所）を指定してファイル内で作業する
		if (!$file) {
			throw new \Exception('ファイルの作成に失敗しました'); //ファイル内でさぎょうができなかった場合の処理
		};

		if (!fputcsv($file, ['id', 'url', 'title', 'company_name', 'features'])) { //ファイルにヘッダを書き込む
			throw new \Exception('ヘッダの書き込みに失敗しました');
		};

		foreach (MynaviJobs::all() as $job) { //MynaviJobsで取得してきた情報を一つずつ取り出す
			if (!fputcsv($file, [$job->id, $job->url, $job->title, $job->company_name, $job->features])) {
				throw new \Exception('ボディの書き込みに失敗しました');
			}
		};

		fclose($file); //fopenしたらfcloseする
	}
}