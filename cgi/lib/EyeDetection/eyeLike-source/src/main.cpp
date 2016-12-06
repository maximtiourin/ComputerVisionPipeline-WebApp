/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

#include <opencv2/objdetect/objdetect.hpp>
#include <opencv2/highgui/highgui.hpp>
#include <opencv2/imgproc/imgproc.hpp>

#include <iostream>
#include <queue>
#include <stdio.h>
#include <stdlib.h>
#include <math.h>

#include "constants.h"
#include "findEyeCenter.h"
#include "findEyeCorner.h"


using namespace std;
using namespace cv;

/** Function Headers */
void detectAndDisplay( cv::Mat frame );

/** Global variables */
cv::String face_cascade_name = "haarcascade_frontalface_alt.xml";
cv::CascadeClassifier face_cascade;
cv::RNG rng(12345);
cv::Mat debugImage;
cv::Mat skinCrCbHist = cv::Mat::zeros(cv::Size(256, 256), CV_8UC1);

int main( int argc, const char** argv ) {
   cv::Mat frame;
   int frame_number;

   // Load the cascades
   if(!face_cascade.load(argv[2])) {
      cout << "-1,-1:-1,-1";
      return -1; 
   }

   //createCornerKernels();
   ellipse(skinCrCbHist, cv::Point(113, 155.6), cv::Size(23.4, 15.2), 43.0, 0.0, 360.0, cv::Scalar(255, 255, 255), -1);

  
   frame = imread(argv[1]);

   // Apply the classifier to the frame
   if(!frame.empty()) {
      detectAndDisplay(frame);
   }
   else {
      cout << "-1,-1:-1,-1"; 
      return -1; 
   }

   return 0;
}

void findEyes(cv::Mat frame_gray, cv::Rect face) {
   cv::Mat faceROI = frame_gray(face);
   cv::Mat debugFace = faceROI;

   if (kSmoothFaceImage) {
    double sigma = kSmoothFaceFactor * face.width;
    GaussianBlur( faceROI, faceROI, cv::Size( 0, 0 ), sigma);
   }
   //-- Find eye regions and draw them
   int eye_region_width = face.width * (kEyePercentWidth/100.0);
   int eye_region_height = face.width * (kEyePercentHeight/100.0);
   int eye_region_top = face.height * (kEyePercentTop/100.0);
   cv::Rect leftEyeRegion(face.width*(kEyePercentSide/100.0),
                         eye_region_top,eye_region_width,eye_region_height);
   cv::Rect rightEyeRegion(face.width - eye_region_width - face.width*(kEyePercentSide/100.0),
                          eye_region_top,eye_region_width,eye_region_height);

   //-- Find Eye Centers
   cv::Point leftPupil = findEyeCenter(faceROI,leftEyeRegion,"Left Eye");
   cv::Point rightPupil = findEyeCenter(faceROI,rightEyeRegion,"Right Eye");


   // change eye centers to face coordinates
   leftPupil.x += leftEyeRegion.x + face.x;
   leftPupil.y += leftEyeRegion.y + face.y;
   rightPupil.x += rightEyeRegion.x + face.x;
   rightPupil.y += rightEyeRegion.y + face.y;
   
   int lx, ly, rx, ry = -1;
   if(leftPupil.x >= 0 && leftPupil.y >= 0) {
      lx = leftPupil.x;
      ly = leftPupil.y;
   }
   if(rightPupil.x >= 0 && rightPupil.y >= 0) {
      rx = rightPupil.x;
      ry = rightPupil.y;
   }
  
  	//Output eye coordinates
  	printf("%d,%d:%d,%d", lx, ly, rx, ry);
}

void detectAndDisplay( cv::Mat frame ) {
   std::vector<cv::Rect> faces;

   std::vector<cv::Mat> rgbChannels(3);
   cv::split(frame, rgbChannels);
   cv::Mat frame_gray = rgbChannels[2];

   //-- Detect faces
   face_cascade.detectMultiScale(frame_gray, faces, 1.1, 2, 0 | CV_HAAR_SCALE_IMAGE | CV_HAAR_FIND_BIGGEST_OBJECT, cv::Size(150, 150));

   //-- Find eye coordinates
   if (faces.size() > 0) {
      findEyes(frame_gray, faces[0]);
   }
   else {
      cout << "-1,-1:-1,-1"; 
   }
}
