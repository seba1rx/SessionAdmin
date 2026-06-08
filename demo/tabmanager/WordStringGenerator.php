<?php
/**
 * WordStringGenerator
 * Generates random strings made from a pool of 1000 real words (animals, foods, and names).
 */
class WordStringGenerator
{
    /** @var array<string> */
    protected array $wordPool = [];

    /**
     * Constructor
     * Optionally provide your own pool of words.
     *
     * @param array|null $customPool
     */
    public function __construct(?array $customPool = null)
    {
        if ($customPool && count($customPool) >= 1000) {
            $this->wordPool = $customPool;
        } else {
            $this->generateDefaultPool();
        }
    }

    /**
     * Builds a pool of 1000 mixed words: animals, foods, and names.
     *
     * @return void
     */
    protected function generateDefaultPool(): void
    {
        $animals = [
            'lion','tiger','bear','wolf','eagle','falcon','hawk','panther','leopard','cheetah',
            'rhino','hippo','elephant','giraffe','zebra','buffalo','bison','antelope','kangaroo','koala',
            'penguin','seal','whale','dolphin','shark','octopus','turtle','snake','lizard','crocodile',
            'frog','toad','rabbit','fox','deer','squirrel','otter','moose','raccoon','badger',
            'cat','dog','mouse','rat','bat','owl','crow','parrot','swan','duck'
        ];

        $foods = [
            'apple','banana','grape','orange','peach','pear','mango','papaya','cherry','strawberry',
            'blueberry','raspberry','blackberry','watermelon','melon','kiwi','plum','fig','lemon','lime',
            'carrot','potato','onion','garlic','tomato','broccoli','spinach','lettuce','pepper','corn',
            'rice','pasta','bread','cheese','milk','butter','yogurt','honey','sugar','salt',
            'chicken','beef','pork','fish','shrimp','egg','bacon','ham','sausage','steak'
        ];

        $names = [
            'alex','bella','chris','david','emma','frank','grace','hannah','ian','julia',
            'kevin','lisa','matt','nora','oliver','paul','quinn','rachel','sarah','tom',
            'uma','victor','will','xavier','yara','zoe','carla','marco','leon','isabel',
            'diego','sofia','pablo','valeria','daniel','maria','lucas','ana','santiago','elena',
            'luis','camila','jorge','adriana','felipe','natalia','ricardo','carolina','mateo','fernanda'
        ];

        // Combine and repeat to reach roughly 1000 entries
        $combined = array_merge($animals, $foods, $names);
        while (count($combined) < 1000) {
            $combined = array_merge($combined, $combined);
        }

        $this->wordPool = array_slice($combined, 0, 1000);
    }

    /**
     * Generates a random string of words from the pool.
     *
     * @param int $numWords Number of words to include
     * @param string $separator Separator between words
     * @return string
     */
    public function generate(int $numWords = 3, string $separator = '-'): string
    {
        $numWords = max(1, $numWords);
        $indexes = array_rand($this->wordPool, $numWords);
        if (!is_array($indexes)) {
            $indexes = [$indexes];
        }

        $words = array_map(fn($i) => $this->wordPool[$i], $indexes);
        return implode($separator, $words);
    }

    /**
     * Returns the total size of the current pool.
     *
     * @return int
     */
    public function getPoolSize(): int
    {
        return count($this->wordPool);
    }
}
