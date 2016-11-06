#include "lib/local/LandmarkDetector/include/LandmarkCoreIncludes.h"

// System includes
#include <iostream>

// OpenCV includes
#include "lib/3rdParty/OpenCV3.1/include/opencv2/core/core.hpp"
#include "lib/3rdParty/OpenCV3.1/include/opencv2/highgui/highgui.hpp"
#include "lib/3rdParty/OpenCV3.1/include/opencv2/imgproc.hpp"

#include "lib/3rdParty/dlib/include/dlib/image_processing/frontal_face_detector.h"

#include "lib/3rdParty/tbb/include/tbb/tbb.h"

void convert_to_grayscale(const cv::Mat& in, cv::Mat& out)
{
	if(in.channels() == 3)
	{
		// Make sure it's in a correct format
		if(in.depth() != CV_8U)
		{
			if(in.depth() == CV_16U)
			{
				cv::Mat tmp = in / 256;
				tmp.convertTo(tmp, CV_8U);
				cv::cvtColor(tmp, out, CV_BGR2GRAY);
			}
		}
		else
		{
			cv::cvtColor(in, out, CV_BGR2GRAY);
		}
	}
	else if(in.channels() == 4)
	{
		cv::cvtColor(in, out, CV_BGRA2GRAY);
	}
	else
	{
		if(in.depth() == CV_16U)
		{
			cv::Mat tmp = in / 256;
			out = tmp.clone();
		}
		else if(in.depth() != CV_8U)
		{
			in.convertTo(out, CV_8U);
		}
		else
		{
			out = in.clone();
		}
	}
}

void write_out_landmarks(const LandmarkDetector::CLNF& clnf_model)
{
	int n = clnf_model.patch_experts.visibilities[0][0].rows;

	for (int i = 0; i < n; ++i) {
		// Use matlab format, so + 1
		std::cout << clnf_model.detected_landmarks.at<double>(i) + 1 << " " << clnf_model.detected_landmarks.at<double>(i + n) + 1 << std::endl;
	}
}

int main(int argc, char** argv) {	
	if (argc >= 2) {
	   //Init
	   LandmarkDetector::FaceModelParameters det_parameters(); //default argument 'arguments', removed to test if i can get away with it
	   det_parameters.validate_detections = false;
	   
      LandmarkDetector::CLNF clnf_model(det_parameters.model_location);
	
	   cv::CascadeClassifier classifier(det_parameters.face_detector_location);
	
	
	   string imagepath = argv[1];
	
	   
	   // Loading image
	   cv::Mat read_image = cv::imread(imagepath, -1);

	   if (read_image.empty()) {
		   std::cout << "Could not read the input image" << std::endl;
		   return 1;
	   }
	   
	   //Convert to greyscale
	   cv::Mat_<uchar> grayscale_image;
		convert_to_grayscale(read_image, grayscale_image);
	   
	   
      bool success = LandmarkDetector::DetectLandmarksInImage(grayscale_image, clnf_model, det_parameters);

      if (success) {
         write_out_landmarks(clnf_model);
      }
      else {
         //No Landmarks Detected
         std::cout << "No landmarks detected." << std::endl;
         return 1;
      }
   }
   
   return 0;
}
