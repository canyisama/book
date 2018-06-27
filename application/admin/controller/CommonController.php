<?php

namespace app\admin\controller;

class CommonController extends BaseController
{

    public function uploadAction()
    {
        $file = request()->file('file');
        $error = $_FILES['file']['error'];
        if ($error) {
            $this->error('上传失败，' . $error);
        }
        $folder = input('folder');
        $dir = ROOT_PATH . 'public' . config('upload_path');
        if ($folder) $dir .= $folder;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $info = $file->move($dir);

        $saveName = $info->getSaveName();
        $saveName = str_replace('\\', '/', $saveName);
        if ($folder) $saveName = $folder . '/' . $saveName;
        $data['fileName'] = $saveName;
        $data['filePath'] = config('upload_path') . $saveName;
        if ($file->getError()) {
            $this->echoError($file->getError());
        } else {
            $this->echoSuccess($data);
        }
    }

}