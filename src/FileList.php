<?php
namespace carry0987\FileList;

use carry0987\FileList\Exceptions\FileListException;
use \FilesystemIterator as FI;
use \RecursiveIteratorIterator as RII;
use \RecursiveDirectoryIterator as RDI;
use \RecursiveCallbackFilterIterator as RCFI;

class FileList
{
    private $max_depth = self::NO_MAX_DEPTH;
    private $ignore_exist = true;
    private $ignore_file = false;
    private $sort_file = false;
    private $modify_item = false;
    private $item_map = [];
    private $set_parent = ['id' => null, 'parent_tree' => null];
    private $parent_tree = [];
    private $allowed_file_type = ['txt', 'jpeg', 'jpg', 'png', 'gif', 'webp'];
    private $file_regex = '/^.+(.jpe?g|.png|.gif|.webp)$/i';
    private $match_regex = false;
    private $append_id = ['dir' => 0, 'file' => 0];
    private $check_already_exist = true;
    private $root_path = null;
    private $set_path = null;
    private $iterator_path;
    private $item_array = [];

    const ITEM_DIR = 'dir';
    const ITEM_FILE = 'file';
    const NO_MAX_DEPTH = -1;
    const NO_FILTER = -1;

    public function __construct()
    {
        $this->item_map[self::ITEM_DIR] = [];
        $this->item_map[self::ITEM_FILE] = [];
    }

    public function maxDepth(int $value)
    {
        $this->max_depth = $value;

        return $this;
    }

    public function ignoreExist(bool $value = true)
    {
        $this->ignore_exist = $value;

        return $this;
    }

    public function ignoreFile(bool $value = false)
    {
        $this->ignore_file = $value;

        return $this;
    }

    public function sortFile(bool $value = false)
    {
        $this->sort_file = $value;

        return $this;
    }

    public function setModifyItem(bool $value = true)
    {
        $this->modify_item = $value;

        return $this;
    }

    public function setAppend(int $append_dir, int $append_file)
    {
        $this->append_id[self::ITEM_DIR] = $append_dir;
        $this->append_id[self::ITEM_FILE] = $append_file;

        return $this;
    }

    public function setAllowedFileType(array|string|int $value)
    {
        if (!is_array($value) && $value !== self::NO_FILTER) {
            $value = explode(',', $value);
        }
        $this->allowed_file_type = $value;

        return $this;
    }

    public function getAllowedFileType()
    {
        return $this->allowed_file_type;
    }

    public function matchRegex(bool $value)
    {
        $this->match_regex = $value;

        return $this;
    }

    public function setParent(int $parent_id, string $parent_tree)
    {
        $this->set_parent['id'] = $parent_id;
        $this->set_parent['parent_tree'] = $parent_tree;

        return $this;
    }

    public function setRootPath(string $value)
    {
        $this->root_path = $value;

        return $this;
    }

    public function setPath(string $path)
    {
        if (!is_readable($path)) {
            throw new FileListException('Cannot read directory ['.$path.']');
        }
        $this->set_path = $path;
        $this->iterator_path = $this->setRDI($path);
        $this->iterator_path->setMaxDepth($this->max_depth);

        return $this;
    }

    public function startRDI()
    {
        $result_dir = $this->recursiveDir($this->iterator_path);
        if ($this->modify_item === false && $this->ignore_file === false) {
            $this->recursiveFile($result_dir, $this->ignore_exist);
        }

        return $this;
    }

    public function getModifyItem(FileList $type = self::ITEM_DIR)
    {
        return isset($this->item_array[$type]) ? $this->item_array[$type] : null;
    }

    public function changeItemID(array $array, int|string $old_key, int|string $new_key, FileList|string $type = self::ITEM_DIR)
    {
        if (!array_key_exists($old_key, $array)) {
            return $array;
        }
        if ($this->check_already_exist === true) {
            $array[$old_key]['already_exist'] = true;
        }
        $this->item_map[$type][$old_key] = $new_key;
        $keys = array_keys($array);
        $keys[array_search($old_key, $keys)] = $new_key;

        return array_combine($keys, $array);
    }

    public function replaceModifyItem(array $array, FileList $type = self::ITEM_DIR)
    {
        $data_type = $type;
        $this->item_array[$data_type] = $array;
        if ($data_type === self::ITEM_DIR) {
            $item_array = &$this->item_array[$data_type];
            //Replace parent_id with exist parent_id
            $parent_id_map = [];
            $reset_append = $this->append_id[$data_type];
            foreach ($item_array as $key => $value) {
                if (isset($this->item_map[$data_type][$value['parent_id']])) {
                    $item_array[$key]['parent_id'] = $this->item_map[$data_type][$value['parent_id']];
                }
                if (!isset($value['already_exist'])) {
                    $get_reset_append = $reset_append++;
                    $parent_id_map[$key] = $get_reset_append;
                }
            }
            if ($this->modify_item === true) {
                $this->check_already_exist = false;
                //Reorder item array
                foreach ($item_array as $key => $value) {
                    if (isset($parent_id_map[$value['parent_id']])) {
                        $item_array[$key]['parent_id'] = $parent_id_map[$value['parent_id']];
                    }
                    if (isset($parent_id_map[$key])) {
                        $item_array = $this->changeItemID($item_array, $key, $parent_id_map[$key], $data_type);
                    }
                }
                $this->check_already_exist = true;
                //Make parent tree
                $this->setParentTree();
                //Start file RDI
                if ($this->ignore_file === false) {
                    $this->recursiveFile($item_array, $this->ignore_exist);
                }
            }
        }

        return $this;
    }

    public function getResult(FileList $result_type = null)
    {
        if ($this->set_parent['id'] !== null) {
            $parent_info = $this->set_parent;
            $array = &$this->item_array;
            foreach ($array[self::ITEM_DIR] as $key => $value) {
                if ($value['parent_id'] === null) {
                    $array[self::ITEM_DIR][$key]['parent_id'] = $parent_info['id'];
                }
                $array[self::ITEM_DIR][$key]['parent_tree'] = $parent_info['parent_tree'].','.$value['parent_tree'];
            }
        }

        return ($result_type !== null) ? ($this->item_array[$result_type] ?? null) : $this->item_array;
    }

    private function setRDI(string $path) {
        $directory = new RDI($path, RDI::SKIP_DOTS | RDI::FOLLOW_SYMLINKS);
        if ($this->match_regex === true) {
            $filter = new RCFI($directory, function($current) {
                return $current->isDir() || preg_match($this->file_regex, $current->getPathname()) > 0;
            });
            return new RII($filter, RII::SELF_FIRST);
        }
        $filter = new RCFI($directory, function($current) {
            return ($current->isDir() && $current->getFilename()[0] !== '.');
        });

        return new RII($filter, RII::SELF_FIRST);
    }

    private function getExtension(string $get_path)
    {
        return pathinfo($get_path, PATHINFO_EXTENSION);
    }

    private function getCurrentName(string $get_path)
    {
        return basename($get_path);
    }

    private function getParentDir(string $get_path)
    {
        return dirname($get_path);
    }

    private function trimPath(string $get_path)
    {
        if ($this->root_path !== null) {
            return str_replace($this->root_path, '', $get_path);
        } else {
            return $get_path;
        }
    }

    private function checkFileExtension(mixed $file)
    {
        if ($this->allowed_file_type === self::NO_FILTER) return true;

        return in_array(strtolower($file->getExtension()), $this->allowed_file_type);
    }

    private function createItemArray(mixed $item_info, FileList|string $item_type = null)
    {
        //Set append key for array
        $append_id = $this->append_id;
        if ($item_type !== null) {
            if (!isset($this->item_array[$item_type])) {
                $this->item_array[$item_type] = [];
            }
            if ($append_id[$item_type] !== 0 && !isset($this->item_array[$item_type][$append_id[$item_type]])) {
                $this->item_array[$item_type][$append_id[$item_type]] = $item_info;
            } else {
                $this->item_array[$item_type][] = $item_info;
            }
        } else {
            if ($append_id[$item_type] !== 0 && !isset($this->item_array[$append_id[$item_type]])) {
                $this->item_array[$append_id[$item_type]] = $item_info;
            } else {
                $this->item_array[] = $item_info;
            }
        }
    }

    private function getItemParamArray(string $path, FileList|string $item_type = null)
    {
        $get_item = false;
        $search_item = $this->item_array;
        if ($item_type !== null) {
            if (!isset($this->item_array[$item_type])) {
                $this->item_array[$item_type] = [];
            }
            $search_item = $this->item_array[$item_type];
        }
        foreach ($search_item as $key => $value) {
            if ($value['file_path'] === $path) {
                $get_item = $key;
            }
        }

        return $get_item;
    }

    /**
     * Unused method
     * It can get directory recursively
     */
    private function getDirectoryList(string $dir)
    {
        $dir_list = $file_list = [];
        //Filetype Filter
        $filetype_list = $this->allowed_file_type;
        //Iterator
        $iterator = new FI($dir, FI::SKIP_DOTS);
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $filetype = pathinfo($item->getFilename(), PATHINFO_EXTENSION);
                if (in_array(strtolower($filetype), $filetype_list)) {
                    $file_list[$item->getFilename()] = $item->getFilename();
                }
            }
            if ($item->isDir()) {
                $dir_list[$item->getFilename()] = $this->getDirectoryList($item->getPathname());
            }
        }
        //Sort directory list by natural order
        uksort($dir_list, 'strnatcmp');
        //Sort file list by natural order
        natsort($file_list);

        return $dir_list + $file_list;
    }

    private function getFileList(string $dir) {
        $current_time = time();
        $file_list = [];

        // Use direct method calls instead of storing to variables
        $iterator = new FI($dir, FI::SKIP_DOTS);

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;
            if ($file->isFile() && $this->checkFileExtension($file)) {
                $full_path = $this->trimPath($file->getPathname());
                $parent_dir = $this->getParentDir($full_path);
                $file_list[$file->getFilename()] = [
                    'file_name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'file_type' => $file->getExtension(),
                    'created_date' => $file->getMTime(),
                    'last_modified' => $file->getMTime(),
                    'file_path' => $full_path,
                    'parent_id' => $this->getItemParamArray($parent_dir, self::ITEM_DIR) ?: $this->set_parent['id'],
                    'post_date' => $current_time
                ];
            }
        }
        if ($this->sort_file) {
            uksort($file_list, 'strnatcmp');
        }
        array_map([$this, 'createItemArray'], $file_list, array_fill(0, count($file_list), self::ITEM_FILE));

        return;
    }

    private function recursiveDir(mixed $iterator)
    {
        $dir_array = array('parent_id' => null);
        $dir_array['last_modified'] = $dir_array['post_date'] = time();
        //Get specific item name
        $item_name = self::ITEM_DIR;
        //Check directory
        foreach ($iterator as $name => $object) {
            $full_path = $this->trimPath($object->getPathname());
            $dir_depth = $iterator->getDepth();
            //Set directory info
            $dir_array['dir_name'] = $object->getFilename();
            $dir_array['file_path'] = $full_path;
            //Prepare directory info for checking existence
            $parent_dir = $this->getParentDir($full_path);
            //Start create rows
            if ($dir_depth > 0) {
                //Check if parent directory exist
                $check_parent = $this->getItemParamArray($parent_dir, $item_name);
                if ($check_parent === false) {
                    $dir_array['dir_name'] = $this->getCurrentName($parent_dir);
                    $dir_array['file_path'] = $parent_dir;
                    $this->createItemArray($dir_array, $item_name);
                } else {
                    if ($this->getItemParamArray($full_path, $item_name) === false) {
                        $dir_array['parent_id'] = $check_parent;
                        $this->createItemArray($dir_array, $item_name);
                    }
                }
            } else {
                if ($this->getItemParamArray($full_path, $item_name) === false) {
                    $dir_array['parent_id'] = null;
                    $this->createItemArray($dir_array, $item_name);
                }
            }
        }
        if ($this->modify_item === false) {
            //Make parent tree
            $this->setParentTree();
        }

        return isset($this->item_array[$item_name]) ? $this->item_array[$item_name] : null;
    }

    private function recursiveFile(array $dir_list, bool $ignore_exist = true)
    {
        //Check file
        $this->getFileList($this->set_path);
        foreach ($dir_list as $key => $value) {
            if ($ignore_exist === true && isset($value['already_exist'])) continue;
            $this->getFileList($this->root_path.$value['file_path']);
        }

        return isset($this->item_array[self::ITEM_FILE]) ? $this->item_array[self::ITEM_FILE] : null;
    }

    private function getParentTree(mixed $item_id, array $item_array)
    {
        if ($item_array[$item_id]['parent_id'] !== null) {
            $this->getParentTree($item_array[$item_id]['parent_id'], $item_array);
        }
        $this->parent_tree[] = $item_id;

        return $this->parent_tree;
    }

    private function setParentTree()
    {
        $item_name = self::ITEM_DIR;
        foreach ($this->item_array[$item_name] as $key => $value) {
            $parent_tree_array = $this->getParentTree($key, $this->item_array[$item_name]);
            $this->item_array[$item_name][$key]['parent_tree'] = implode(',', $parent_tree_array);
            $this->parent_tree = [];
        }
    }
}
